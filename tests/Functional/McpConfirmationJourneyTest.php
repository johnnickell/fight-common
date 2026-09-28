<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Common\Adapter\Mcp\Sodium\SodiumMcpStateProtector;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Tool\Interaction\McpConfirmationOutcome;
use Fight\Common\Application\Mcp\Tool\Interaction\McpToolInteraction;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\Domain\Serialization\SampleCommand;
use Fight\Test\Common\Domain\Serialization\SampleEvent;
use Fight\Test\Common\Fixture\Mcp\DestructiveValueTool;
use Fight\Test\Common\Fixture\Mcp\LockedConfirmationStore;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

#[CoversNothing]
final class McpConfirmationJourneyTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) { unlink($file); }
        parent::tearDown();
    }

    public function test_that_a_consumer_store_preserves_atomic_binding_expiry_and_consumed_identity(): void
    {
        $path = $this->file();
        $store = new LockedConfirmationStore($path);
        $id = str_repeat('a', 64);
        $binding = str_repeat('b', 64);
        $expires = time() + 300;
        self::assertSame(McpConfirmationOutcome::ABSENT, $store->consume($id, $binding, $expires));
        $store->issue($id, $binding, $expires);
        self::assertSame(McpConfirmationOutcome::MISMATCHED, $store->consume($id, str_repeat('c', 64), $expires));
        self::assertSame(McpConfirmationOutcome::MISMATCHED, $store->consume($id, $binding, $expires + 1));
        $lock = fopen($path, 'r+');
        try {
            self::assertTrue(flock($lock, LOCK_EX));
            self::assertSame(McpConfirmationOutcome::CONTENDED, $store->consume($id, $binding, $expires));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        self::assertSame(McpConfirmationOutcome::CONSUMED, $store->consume($id, $binding, $expires));
        self::assertSame(McpConfirmationOutcome::ALREADY_CONSUMED, new LockedConfirmationStore($path)->consume($id, $binding, $expires));
        try {
            $store->issue($id, $binding, $expires);
            self::fail('A consumed identity was replaced');
        } catch (RuntimeException $failure) {
            self::assertSame('Confirmation identity exists', $failure->getMessage());
        }
        self::assertSame(McpConfirmationOutcome::ALREADY_CONSUMED, $store->consume($id, $binding, $expires));
        $expired = str_repeat('d', 64);
        $boundary = time();
        $store->issue($expired, $binding, $boundary);
        self::assertSame(McpConfirmationOutcome::EXPIRED, $store->consume($expired, $binding, $boundary));
    }

    public function test_that_concurrent_semantic_retries_have_one_winner_and_one_command_event_effect(): void
    {
        $state = $this->file();
        $effects = $this->file();
        $parent = $this->responder($state, $effects);
        $issued = $parent->respond($this->wire(['name' => 'value.delete', 'arguments' => ['id' => 'original']], 1))->toArray();
        self::assertSame('input_required', $issued['result']['resultType']);
        self::assertSame('', file_get_contents($effects));
        $parameters = [
            'name' => 'value.delete', 'arguments' => ['id' => 'original'], 'requestState' => $issued['result']['requestState'],
            'inputResponses' => ['approval' => ['action' => 'accept', 'content' => ['confirm' => true]], 'reason' => ['action' => 'accept', 'content' => ['text' => 'fixture reason']]]
        ];
        $children = [];
        try {
            for ($index = 0; $index < 2; ++$index) {
                $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
                self::assertNotFalse($pair);
                $resultPath = $this->file();
                $pid = pcntl_fork();
                self::assertNotSame(-1, $pid);
                if ($pid === 0) {
                    fclose($pair[0]);
                    stream_set_timeout($pair[1], 10);
                    try {
                        fwrite($pair[1], 'ready');
                        if (fread($pair[1], 1) !== 'g') { exit(2); }
                        // Fresh composition and a separately opened store in each contender process.
                        $result = $this->responder($state, $effects)->respond($this->wire($parameters, $index + 2))->toArray();
                        file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR));
                        exit(0);
                    } catch (Throwable) {
                        exit(3);
                    }
                }
                fclose($pair[1]);
                stream_set_timeout($pair[0], 10);
                $children[] = [$pid, $pair[0], $resultPath];
            }
            // Both processes must reach the start barrier before either retry may run.
            foreach ($children as [, $socket]) { self::assertSame('ready', fread($socket, 5)); }
            foreach ($children as [, $socket]) { self::assertSame(1, fwrite($socket, 'g')); }
            $results = [];
            foreach ($children as [$pid, $socket, $path]) {
                $deadline = microtime(true) + 10;
                do {
                    $waited = pcntl_waitpid($pid, $status, WNOHANG);
                    if ($waited !== 0) { break; }
                    usleep(1000);
                } while (microtime(true) < $deadline);
                self::assertSame($pid, $waited, 'Contender did not terminate within ten seconds');
                self::assertTrue(pcntl_wifexited($status));
                self::assertSame(0, pcntl_wexitstatus($status));
                $results[] = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            }
            $winners = array_values(array_filter($results, fn(array $result): bool => isset($result['result'])));
            $losers = array_values(array_filter($results, fn(array $result): bool => isset($result['error'])));
            self::assertCount(1, $winners);
            self::assertCount(1, $losers);
            self::assertSame('complete', $winners[0]['result']['resultType']);
            self::assertSame(['code' => -32602, 'message' => 'Invalid params.'], $losers[0]['error']);
            self::assertSame("command:original\nevent:original\n", file_get_contents($effects));
            $replay = $this->responder($state, $effects)->respond($this->wire($parameters, 4))->toArray();
            self::assertSame($losers[0]['error'], $replay['error']);
            self::assertSame("command:original\nevent:original\n", file_get_contents($effects));
        } finally {
            foreach ($children as [$pid, $socket]) {
                fclose($socket);
                if (pcntl_waitpid($pid, $status, WNOHANG) === 0) {
                    posix_kill($pid, SIGKILL);
                    pcntl_waitpid($pid, $status);
                }
            }
        }
    }

    private function responder(string $state, string $effects): McpResponder
    {
        $events = new SimpleEventDispatcher();
        $events->addHandler(ClassName::underscore(SampleEvent::class), static function ($message) use ($effects): void {
            file_put_contents($effects, 'event:'.$message->payload()->toArray()['value']."\n", FILE_APPEND | LOCK_EX);
        });
        $handler = new class($effects, $events) implements CommandHandler {
            public function __construct(private string $effects, private SimpleEventDispatcher $events) {}
            public static function commandRegistration(): string { return SampleCommand::class; }
            public function handle(CommandMessage $commandMessage): void {
                $value = $commandMessage->payload()->toArray()['value'];
                file_put_contents($this->effects, 'command:'.$value."\n", FILE_APPEND | LOCK_EX);
                $this->events->trigger(new SampleEvent($value));
            }
        };
        $router = new InMemoryCommandRouter();
        $router->registerHandler(SampleCommand::class, $handler);
        $commands = new RoutingCommandBus($router);
        $tool = new DestructiveValueTool(static fn(ApplicationData $input) => $commands->execute(new SampleCommand($input->get('id'))));
        $registry = new McpToolRegistry([$tool]);
        $availability = new class implements McpToolAvailability {
            public function isAvailable(McpToolInfo $tool): bool { return true; }
        };
        $interaction = new McpToolInteraction(new SodiumMcpStateProtector(1, [1 => str_repeat('k', 32)]), 'caller', confirmations: new LockedConfirmationStore($state));
        return new McpResponder(new McpCapabilityRegistry(new McpServerInfo('confirmation-fixture', '1'), [
            new McpToolDiscovery($registry, $availability, str_repeat('c', 32)),
            new McpToolInvocation(new McpToolInvoker($registry, $availability, interaction: $interaction))
        ]));
    }

    private function file(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mcp-confirmation-');
        $this->files[] = $path;
        return $path;
    }

    private function wire(array $parameters, int $id): string
    {
        $parameters['_meta'] = ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => ['elicitation' => ['form' => (object) []]]];
        return json_encode(['jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/call', 'params' => $parameters], JSON_THROW_ON_ERROR);
    }
}
