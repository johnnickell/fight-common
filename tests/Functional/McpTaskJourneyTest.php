<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Task\Dbal\DbalTaskSchema;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Test\Common\Fixture\Mcp\TaskEndpoint;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class McpTaskJourneyTest extends UnitTestCase
{
    #[DataProvider('compositions')]
    public function test_that_real_http_start_disconnect_independent_input_continuation_and_reconnect_use_actual_suppliers(string $backend, string $composition): void
    {
        $this->journey($backend, $composition, 'input');
    }

    #[DataProvider('outcomes')]
    public function test_that_http_cancellation_is_only_intent_and_independent_outcomes_survive_reconnect(string $backend, string $outcome): void
    {
        $this->journey($backend, 'psr', $outcome);
    }

    #[DataProvider('faults')]
    public function test_that_response_loss_submission_and_postcommit_publication_failure_leave_pollable_durable_state(string $backend, string $fault): void
    {
        $this->journey($backend, 'psr', $fault);
    }

    private function journey(string $backend, string $composition, string $scenario): void
    {
        $directory = sys_get_temp_dir().'/fight-task-http-'.bin2hex(random_bytes(8)); self::assertTrue(mkdir($directory, 0700));
        $c = $this->database($backend, $directory);
        file_put_contents($directory.'/config.json', json_encode(['params' => $c->getParams()], JSON_THROW_ON_ERROR));
        $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error); self::assertIsResource($reservation, $error);
        $address = stream_socket_get_name($reservation, false); fclose($reservation);
        $server = new Process([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'zlib.output_compression=0', '-d', 'display_errors=0', '-S', $address, 'tests/Fixture/Mcp/task-server.php'], dirname(__DIR__, 2), ['FIGHT_TASK_HTTP_DIRECTORY' => $directory], timeout: 60);
        $trace = [];
        try {
            $server->start(); $ready = false;
            for ($attempt = 0; $attempt < 300; ++$attempt) { $probe = @stream_socket_client('tcp://'.$address, $errno, $error, 0.05); if (is_resource($probe)) { fclose($probe); $ready = true; break; } usleep(10000); }
            self::assertTrue($ready, $server->getErrorOutput());
            $path = '/mcp?composition='.$composition.'&scenario='.$scenario;
            $call = function (string $method, array $params, bool $loseResponse = false) use ($address, $path, &$trace): ?array {
                $request = TaskEndpoint::request($method, $params);
                $socket = stream_socket_client('tcp://'.$address, $errno, $error, 5); self::assertIsResource($socket, $error); stream_set_timeout($socket, 5);
                $body = (string) $request->getBody(); $wire = "POST $path HTTP/1.1\r\nHost: $address\r\nConnection: close\r\nContent-Length: ".strlen($body)."\r\n";
                foreach ($request->getHeaders() as $name => $values) { foreach ($values as $value) { $wire .= "$name: $value\r\n"; } }
                fwrite($socket, $wire."\r\n".$body);
                if ($loseResponse) { stream_socket_shutdown($socket, STREAM_SHUT_RDWR); fclose($socket); $trace[] = ['method' => $method, 'response' => 'deliberately lost']; return null; }
                $response = stream_get_contents($socket); self::assertFalse(stream_get_meta_data($socket)['timed_out']); fclose($socket);
                [$headers, $json] = explode("\r\n\r\n", $response, 2);
                self::assertStringContainsString('application/json', strtolower($headers)); self::assertStringNotContainsString('private', $json);
                $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR); $trace[] = ['method' => $method, 'headers' => $headers, 'body' => $data]; return $data;
            };
            if ($scenario === 'storage-failure') {
                if ($backend === 'sqlite') { $c->executeStatement("CREATE TRIGGER reject_task_creation BEFORE INSERT ON fight_tasks BEGIN SELECT RAISE(ABORT, 'private'); END"); }
                else { $c->executeStatement("ALTER TABLE fight_tasks ADD CONSTRAINT reject_task_creation CHECK (scope <> 'scope')"); }
                self::assertSame(-32603, $call('tools/call', ['name' => 'example.operation'])['error']['code']);
                self::assertSame(0, (int) $c->fetchOne('SELECT COUNT(*) FROM fight_tasks'));
                if ($backend === 'sqlite') { $c->executeStatement('DROP TRIGGER reject_task_creation'); }
                elseif ($backend === 'mysql') { $c->executeStatement('ALTER TABLE fight_tasks DROP CHECK reject_task_creation'); }
                else { $c->executeStatement('ALTER TABLE fight_tasks DROP CONSTRAINT reject_task_creation'); }
            }
            $start = $call('tools/call', ['name' => 'example.operation'], $scenario === 'response-loss');
            if ($scenario === 'response-loss') {
                $deadline = microtime(true) + 5;
                while (!file_exists($directory.'/requests.jsonl') && microtime(true) < $deadline) { usleep(10000); }
                self::assertFileExists($directory.'/requests.jsonl');
            } elseif (in_array($scenario, ['submission-failure', 'publication-failure'], true)) { self::assertSame(-32603, $start['error']['code']); }
            else { self::assertSame('task', $start['result']['resultType']); self::assertSame('working', $start['result']['status']); }
            // This new socket and the producer process share only committed reusable storage, not the HTTP object.
            $poll = $call('tasks/get', ['taskId' => TaskFixture::ID]); self::assertSame('working', $poll['result']['status']);
            $fresh = new TaskFixture(DriverManager::getConnection($c->getParams()));
            self::assertSame(1, $fresh->get()->get('version')); self::assertNull($fresh->cancellation()->get('cancellation'));
            if ($scenario === 'input') {
                $worker = $this->worker($directory, 'input'); $trace[] = ['producer' => $worker];
                $pending = $call('tasks/get', ['taskId' => TaskFixture::ID])['result']; self::assertSame('input_required', $pending['status']); self::assertCount(2, $pending['inputRequests']);
                self::assertSame('elicitation/create', $pending['inputRequests'][TaskFixture::KEY_1]['method']);
                $bad = $call('tasks/update', ['taskId' => TaskFixture::ID, 'inputResponses' => (object) [TaskFixture::KEY_1 => ['action' => 'accept', 'content' => (object) ['answer' => 'yes']], TaskFixture::KEY_2 => ['action' => 'accept', 'content' => (object) ['answer' => 7]]]]);
                self::assertSame(-32602, $bad['error']['code']); self::assertCount(0, $fresh->observe()->get('groups')->get(TaskFixture::KEY_1)->get('answers')->properties());
                $ack = $call('tasks/update', ['taskId' => TaskFixture::ID, 'inputResponses' => (object) [TaskFixture::KEY_1 => self::accept()]]); self::assertSame('complete', $ack['result']['resultType']);
                $partial = $call('tasks/get', ['taskId' => TaskFixture::ID])['result']; self::assertCount(1, $partial['inputRequests']); self::assertArrayHasKey(TaskFixture::KEY_2, $partial['inputRequests']);
                $call('tasks/cancel', ['taskId' => TaskFixture::ID]);
                $call('tasks/update', ['taskId' => TaskFixture::ID, 'inputResponses' => (object) [TaskFixture::KEY_1 => false, TaskFixture::KEY_2 => self::accept(), 'unknown' => false]]);
                $worker = $this->worker($directory, 'continue'); $trace[] = ['producer' => $worker];
                self::assertTrue($worker['effect_attempted']); self::assertTrue($worker['input']['groups'][TaskFixture::KEY_1]['consumed']);
                self::assertNotNull($worker['cancellation']['cancellation']); self::assertSame(TaskConflict::class, $worker['late']);
                $result = $call('tasks/get', ['taskId' => TaskFixture::ID])['result']; self::assertSame('completed', $result['status']); self::assertTrue($result['result']['isError']);
            } elseif (in_array($scenario, ['completed', 'failed', 'cancelled'], true)) {
                $this->worker($directory, 'input');
                $ack = $call('tasks/cancel', ['taskId' => TaskFixture::ID]); self::assertSame('complete', $ack['result']['resultType']);
                $observer = $this->worker($directory, 'observe'); self::assertFalse($observer['effect_attempted']); self::assertSame('input_required', $observer['view']['state']['status']); self::assertNotNull($observer['cancellation']['cancellation']);
                $worker = $this->worker($directory, $scenario); $trace[] = ['producer' => $worker]; self::assertSame(TaskConflict::class, $worker['late']);
                $result = $call('tasks/get', ['taskId' => TaskFixture::ID])['result']; self::assertSame($scenario, $result['status']);
                if ($scenario === 'completed') { self::assertFalse($result['result']['isError']); }
                if ($scenario === 'failed') { self::assertSame(['code' => -32603, 'message' => 'Internal error'], $result['error']); }
                $before = $fresh->get()->toString(); $call('tasks/cancel', ['taskId' => TaskFixture::ID]); self::assertSame($before, $fresh->get()->toString());
            } else {
                $trace[] = ['producer' => $this->worker($directory, 'completed')];
                self::assertSame('completed', $call('tasks/get', ['taskId' => TaskFixture::ID])['result']['status']);
            }
            // Revocation, scope isolation and controlled disappearance cannot become success or expiry claims.
            file_put_contents($directory.'/flags.json', json_encode(['readAllowed' => false])); self::assertSame(-32602, $call('tasks/get', ['taskId' => TaskFixture::ID])['error']['code']);
            file_put_contents($directory.'/flags.json', json_encode(['readAllowed' => true, 'discloseAllowed' => false])); self::assertSame(-32602, $call('tasks/get', ['taskId' => TaskFixture::ID])['error']['code']);
            file_put_contents($directory.'/flags.json', '{}'); self::assertSame(-32602, $call('tasks/get', ['taskId' => str_repeat('b', 32)])['error']['code']);
            self::assertNull($fresh->get(scope: 'different-scope'));
            $c->executeStatement('DELETE FROM fight_tasks'); self::assertSame(-32602, $call('tasks/get', ['taskId' => TaskFixture::ID])['error']['code']);
            $c->executeStatement('DROP TABLE fight_tasks'); self::assertSame(-32603, $call('tasks/get', ['taskId' => TaskFixture::ID])['error']['code']);
            self::assertStringNotContainsString('notifications/', json_encode($trace));
        } finally {
            $server->stop(); $c->close();
            $destination = getenv('FIGHT_TASK_EVIDENCE_DIR');
            if ($destination !== false) {
                $prefix = $destination.'/'.$backend.'-'.$composition.'-'.$scenario;
                file_put_contents($prefix.'.json', json_encode($trace, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                file_put_contents($prefix.'.log', $server->getErrorOutput().$server->getOutput());
                if (file_exists($directory.'/requests.jsonl')) { copy($directory.'/requests.jsonl', $prefix.'-requests.jsonl'); }
            }
            $this->removeTemporaryDirectory($directory, 'fight-task-http-');
        }
    }

    private function worker(string $directory, string $mode): array
    {
        $process = new Process([PHP_BINARY, __DIR__.'/../TestCase/Mcp/Task/http-worker.php', $directory.'/config.json', $mode], timeout: 10); $process->mustRun();
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR); self::assertNotSame(getmypid(), $result['pid']); return $result;
    }

    private static function accept(): array { return ['action' => 'accept', 'content' => (object) ['answer' => 'yes']]; }
    public static function compositions(): iterable { foreach (['sqlite', 'mysql', 'postgresql'] as $db) { foreach (['psr', 'symfony', 'codeigniter'] as $composition) { yield $db.'-'.$composition => [$db, $composition]; } } }
    public static function outcomes(): iterable { foreach (['sqlite', 'mysql', 'postgresql'] as $db) { foreach (['completed', 'failed', 'cancelled'] as $outcome) { yield $db.'-'.$outcome => [$db, $outcome]; } } }
    public static function faults(): iterable { foreach (['sqlite', 'mysql', 'postgresql'] as $db) { foreach (['response-loss', 'submission-failure', 'publication-failure', 'storage-failure'] as $fault) { yield $db.'-'.$fault => [$db, $fault]; } } }
    private function database(string $backend, string $directory): Connection
    {
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'path' => $directory.'/task.sqlite']; }
        else { $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN'); self::assertIsString($dsn, 'Real disposable database required'); $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn); }
        $c = DriverManager::getConnection($params); foreach (array_reverse(DbalTaskSchema::TABLES) as $table) { $c->executeStatement('DROP TABLE IF EXISTS '.$table); } (new DbalTaskSchema())->install($c); return $c;
    }
}
