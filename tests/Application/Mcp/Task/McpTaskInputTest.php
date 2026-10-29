<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Task;

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Task\Dbal\DbalTaskSchema;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpRequestMetadata;
use Fight\Common\Application\Mcp\Task\McpTaskInput;
use Fight\Common\Application\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Domain\Task\Event\TaskChanged;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(McpTaskInput::class)]
#[CoversClass(McpProtocolError::class)]
final class McpTaskInputTest extends UnitTestCase
{
    public function test_that_real_method_delegates_to_durable_command_and_returns_only_complete_ack(): void
    {
        $f = $this->fixture(); $f->create(); $f->issue(count: 2);
        $method = new McpTaskInput($f->commands, 'scope', 'caller');
        $reply = $method->handle(self::request(['taskId' => TaskFixture::ID, 'inputResponses' => (object) [TaskFixture::KEY_1 => (object) ['action' => 'accept', 'content' => (object) ['answer' => 'valid']]]]));
        self::assertSame(['resultType' => 'complete'], $reply->toArray());
        self::assertLessThanOrEqual(128, strlen(json_encode($reply->toArray(), JSON_THROW_ON_ERROR)));
        self::assertSame([TaskFixture::KEY_2], array_keys($f->get()->get('state')->get('input')->properties()));
        self::assertSame('valid', $f->observe()->get('groups')->get(TaskFixture::KEY_1)->get('answers')->get(TaskFixture::KEY_1)->get('content')->get('answer'));
        $method->handle(self::request(['taskId' => TaskFixture::ID, 'inputResponses' => (object) [TaskFixture::KEY_1 => false, 'unknown' => false]]));
        self::assertSame(3, $f->get()->get('version'));
        $f->submitAllowed = false;
        $this->protocolFailure(fn () => $method->handle(self::request(['taskId' => TaskFixture::ID, 'inputResponses' => (object) []])), -32602);
    }

    public function test_that_capability_and_parameter_errors_are_safe_and_precede_dispatch(): void
    {
        $f = $this->fixture(); $f->create(); $f->issue(); $method = new McpTaskInput($f->commands, 'scope', 'caller');
        $parameters = ['taskId' => TaskFixture::ID, 'inputResponses' => (object) []];
        $this->protocolFailure(fn () => $method->handle(self::request($parameters, method: 'tasks/get')), -32601);
        $this->protocolFailure(fn () => $method->handle(self::request($parameters, capabilities: [])), -32021, 'io.modelcontextprotocol/tasks');
        $this->protocolFailure(fn () => $method->handle(self::request($parameters, capabilities: ['extensions' => (object) ['io.modelcontextprotocol/tasks' => (object) []]])), -32021, 'elicitation');
        foreach ([[], ['taskId' => TaskFixture::ID], ['taskId' => 1, 'inputResponses' => (object) []], ['taskId' => 'guessed', 'inputResponses' => (object) []], $parameters + ['status' => 'completed'], ['taskId' => TaskFixture::ID, 'inputResponses' => []], ['taskId' => TaskFixture::ID, 'inputResponses' => (object) ['unknown' => str_repeat('x', 32769)]], ['taskId' => TaskFixture::ID, 'inputResponses' => (object) [TaskFixture::KEY_1 => (object) ['action' => 'accept', 'content' => (object) ['answer' => false]]]], ['taskId' => str_repeat('f', 32), 'inputResponses' => (object) []]] as $bad) {
            $this->protocolFailure(fn () => $method->handle(self::request($bad)), -32602);
        }
        self::assertSame(2, $f->get()->get('version'));
    }

    public function test_that_postcommit_delivery_failure_is_not_a_success_ack_or_an_implicit_retry(): void
    {
        $f = $this->fixture(); $f->create(); $f->issue(); $calls = 0;
        $f->events->addHandler(ClassName::underscore(TaskChanged::class), static function () use (&$calls): void { ++$calls; throw new RuntimeException('private dispatch'); });
        $method = new McpTaskInput($f->commands, 'scope', 'caller');
        $request = self::request(['taskId' => TaskFixture::ID, 'inputResponses' => (object) [TaskFixture::KEY_1 => (object) ['action' => 'decline']]]);
        try { $method->handle($request); self::fail('Postcommit failure must propagate to the central safe diagnostic path.'); } catch (CommittedTaskDispatchFailed) { self::assertSame('decline', $f->observe()->get('groups')->get(TaskFixture::KEY_1)->get('answers')->get(TaskFixture::KEY_1)->get('action')); }
        self::assertSame(['resultType' => 'complete'], $method->handle($request)->toArray()); self::assertSame(1, $calls);
    }

    private static function request(array $parameters, ?array $capabilities = null, string $method = 'tasks/update'): McpRequest
    {
        return new McpRequest('request-1', $method, $parameters, McpRequestMetadata::fromObject(StrictJson::fromObject([
            McpRequestMetadata::PROTOCOL_VERSION_KEY => '2026-07-28',
            McpRequestMetadata::CLIENT_CAPABILITIES_KEY => StrictJson::fromObject($capabilities ?? ['extensions' => (object) ['io.modelcontextprotocol/tasks' => (object) []], 'elicitation' => (object) ['form' => (object) []]])
        ])));
    }

    private function fixture(): TaskFixture { $c = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]); (new DbalTaskSchema())->install($c); return new TaskFixture($c); }
    private function protocolFailure(callable $operation, int $code, ?string $required = null): void
    {
        try { $operation(); self::fail('Expected safe protocol error.'); } catch (McpProtocolException $failure) {
            self::assertSame($code, $failure->protocolError()->toArray()['code']);
            if ($required !== null) { self::assertStringContainsString($required, json_encode($failure->protocolError()->toArray(), JSON_UNESCAPED_SLASHES)); }
            self::assertStringNotContainsString('private', json_encode($failure->protocolError()->toArray()));
        }
    }
}
