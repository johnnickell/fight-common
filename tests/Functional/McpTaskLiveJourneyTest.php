<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal\DbalSubscriptionSchema;
use Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal\DbalSubscriptionStore;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskSchema;
use Fight\Common\Application\Mcp\Task\McpTasks;
use Fight\Test\Common\Fixture\Mcp\TaskEndpoint;
use Fight\Test\Common\TestCase\Mcp\LifecycleTables;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\Mcp\Task\TaskLiveDiagnostics;
use Fight\Test\Common\TestCase\Mcp\Task\TaskLiveFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class McpTaskLiveJourneyTest extends UnitTestCase
{
    private string $directory;
    private Connection $connection;
    private LifecycleTables $tables;
    private array $defaultSnapshot = [];
    private array $servers = [];
    private array $trace = [];
    private array $socketRequests = [];

    #[DataProvider('compositions')]
    public function test_that_real_http_native_frames_follow_independent_suppliers_and_reconnect_without_replaying_intermediates(string $backend, string $composition, bool $configured): void
    {
        $this->database($backend, $configured);
        $leftServer = $this->server(); $rightServer = $this->server(); $poll = $this->server();
        $start = $this->rpc($poll, TaskEndpoint::request()); self::assertSame('task', $start['result']['resultType']);
        $left = $this->open($leftServer, $composition, ['taskIds' => [TaskFixture::ID]], 7, 'left');
        $right = $this->open($rightServer, $composition.'&mixed=1', ['taskIds' => [TaskFixture::ID], 'toolsListChanged' => true], '7', 'right');
        self::assertSame(['taskIds' => [TaskFixture::ID]], $this->next($left)['params']['notifications']);
        self::assertSame(['taskIds' => [TaskFixture::ID], 'toolsListChanged' => true], $this->next($right)['params']['notifications']);
        $baseline = $this->next($left); $other = $this->next($right);
        self::assertSame(7, $baseline['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
        self::assertSame('7', $other['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
        $delayed = $this->rpc($poll, TaskEndpoint::request('tasks/get', ['taskId' => TaskFixture::ID]))['result'];
        $this->worker('notice');
        self::assertSame('notifications/tools/list_changed', $this->next($right)['method']);
        $this->worker('input');
        $input = $this->next($left)['params']; $this->next($right);
        self::assertSame('input_required', $input['status']); self::assertCount(2, $input['inputRequests']);
        $this->assertNewer($delayed, $input); self::assertSame($delayed['lastUpdatedAt'], $input['lastUpdatedAt']);
        $accept = ['action' => 'accept', 'content' => (object) ['answer' => 'PRIVATE ANSWER SENTINEL']];
        $update = $this->rpc($poll, TaskEndpoint::request('tasks/update', ['taskId' => TaskFixture::ID, 'inputResponses' => (object) [TaskFixture::KEY_1 => $accept]]));
        self::assertSame('complete', $update['result']['resultType']);
        $partial = $this->next($left)['params']; $this->next($right); self::assertCount(1, $partial['inputRequests']); $this->assertNewer($input, $partial);
        // Intent is private and equal timestamps cannot turn it into a public revision gap or cancelled outcome.
        $this->rpc($poll, TaskEndpoint::request('tasks/cancel', ['taskId' => TaskFixture::ID]));
        $afterIntent = $this->rpc($poll, TaskEndpoint::request('tasks/get', ['taskId' => TaskFixture::ID]))['result'];
        self::assertSame($partial['_meta'][McpTasks::REVISION], $afterIntent['_meta'][McpTasks::REVISION]); self::assertSame('input_required', $afterIntent['status']);
        $this->rpc($poll, TaskEndpoint::request('tasks/update', ['taskId' => TaskFixture::ID, 'inputResponses' => (object) [TaskFixture::KEY_2 => $accept]]));
        $supplier = $this->worker('continue'); self::assertTrue($supplier['effect_attempted']); self::assertNotNull($supplier['cancellation']['cancellation']);
        // Working may be coalesced; observe current completed Tool-error, not a fabricated failed state.
        $terminal = $this->untilStatus($left, 'completed'); $this->untilStatus($right, 'completed');
        self::assertTrue($terminal['result']['isError']); $this->assertNewer($partial, $terminal);
        touch($this->directory.'/revoke-scope');
        self::assertSame([], $this->drain($left)); self::assertSame([], $this->drain($right)); unlink($this->directory.'/revoke-scope');
        $reconnect = $this->open($leftServer, $composition, ['taskIds' => [TaskFixture::ID]], 'reconnect'); $this->next($reconnect);
        $current = $this->next($reconnect)['params']; self::assertSame($terminal['_meta'][McpTasks::REVISION], $current['_meta'][McpTasks::REVISION]);
        touch($this->directory.'/source-failure'); self::assertSame([], $this->drain($reconnect)); unlink($this->directory.'/source-failure');
        $read = $this->rpc($poll, TaskEndpoint::request('tasks/get', ['taskId' => TaskFixture::ID]))['result']; self::assertSame('completed', $read['status']); self::assertSame($current['_meta'][McpTasks::REVISION], $read['_meta'][McpTasks::REVISION]);
        $isolated = $this->open($leftServer, $composition.'&scope=concealed', ['taskIds' => [TaskFixture::ID]], 7); self::assertSame([], $this->next($isolated)['params']['notifications']); $this->disconnect($isolated);
        $this->assertPrivateStorage();
        self::assertFalse($this->connection->isTransactionActive());
    }

    #[DataProvider('outcomes')]
    public function test_that_independent_failed_cancelled_outcomes_tracking_loss_and_disappearance_are_not_successful_idle_streams(string $backend, string $outcome, bool $configured): void
    {
        $this->database($backend, $configured); $fixture = new TaskFixture($this->connection, $this->tables->task); $fixture->create();
        $leftServer = $this->server(); $rightServer = $this->server(); $socket = $this->open($leftServer, 'psr', ['taskIds' => [TaskFixture::ID]]); $this->next($socket); $this->next($socket);
        $this->worker($outcome); $terminal = $this->untilStatus($socket, $outcome);
        if ($outcome === 'failed') { self::assertSame(['code' => -32603, 'message' => 'Internal error'], $terminal['error']); }
        $this->connection->executeStatement($this->sql('UPDATE fight_mcp_subscriptions SET expires_at = ?'), [time()]); self::assertSame([], $this->drain($socket));
        $again = $this->open($rightServer, 'psr', ['taskIds' => [TaskFixture::ID]]); $this->next($again); self::assertSame($outcome, $this->next($again)['params']['status']);
        $before = $fixture->get()->toString();
        touch($this->directory.'/deny-payload'); self::assertSame([], $this->drain($again)); unlink($this->directory.'/deny-payload'); self::assertSame($before, $fixture->get()->toString());
        $missing = $this->open($leftServer, 'psr', ['taskIds' => [TaskFixture::ID]]); $this->next($missing); $this->next($missing); $this->connection->executeStatement($this->sql('DELETE FROM fight_tasks')); self::assertSame([], $this->drain($missing));
        self::assertSame(-32602, $this->rpc($rightServer, TaskEndpoint::request('tasks/get', ['taskId' => TaskFixture::ID]))['error']['code']);
        $this->assertPrivateStorage();
    }

    #[DataProvider('databases')]
    public function test_that_idle_disconnect_abrupt_death_and_graceful_completion_preserve_truthful_native_tracking(string $backend, bool $configured): void
    {
        $this->database($backend, $configured); $fixture = new TaskFixture($this->connection, $this->tables->task); $fixture->create(); $leftServer = $this->server();
        $socket = $this->open($leftServer, 'psr&lifetime=1', ['taskIds' => [TaskFixture::ID]], 'graceful'); $this->next($socket); $this->next($socket);
        $tail = $this->drain($socket); self::assertCount(1, $tail); self::assertSame('graceful', $tail[0]['result']['_meta']['io.modelcontextprotocol/subscriptionId']);
        $socket = $this->open($leftServer, 'psr', ['taskIds' => [TaskFixture::ID]]); $this->next($socket); $this->next($socket); $this->disconnect($socket);
        $deadline = microtime(true) + 3;
        do { $open = (int) $this->connection->fetchOne($this->sql('SELECT COUNT(*) FROM fight_mcp_subscriptions WHERE closed_at = 0')); if ($open === 0) { break; } usleep(10000); } while (microtime(true) < $deadline);
        self::assertSame(0, $open);
        $abrupt = $this->open($leftServer, 'psr&abrupt=1', ['taskIds' => [TaskFixture::ID]]); self::assertSame('notifications/subscriptions/acknowledged', $this->next($abrupt)['method']); $this->drain($abrupt);
        $row = $this->connection->fetchAssociative($this->sql('SELECT * FROM fight_mcp_subscriptions WHERE closed_at = 0')); self::assertIsArray($row);
        $subscriptionStore = new DbalSubscriptionStore($this->connection, tables: $this->tables->subscription); $now = (int) $row['expires_at'];
        try { $subscriptionStore->heartbeat($row['registration_id'], $row['owner'], $now, $now + 5); self::fail('Expired owner cannot revive'); } catch (\RuntimeException) {}
        self::assertSame(3, (new DbalSubscriptionStore($this->connection, tables: $this->tables->subscription))->cleanup($now, 16));
        self::assertSame('working', $fixture->get()->get('state')->get('status')); self::assertNull($fixture->cancellation()->get('cancellation'));
        $this->assertPrivateStorage();
    }

    #[DataProvider('sourceRaces')]
    public function test_that_native_http_source_races_deny_opening_baseline_and_update_after_revocation_or_owner_close(string $backend, string $fault, string $phase, bool $configured): void
    {
        $this->database($backend, $configured); $fixture = new TaskFixture($this->connection, $this->tables->task); $fixture->create();
        $server = $this->server();
        $socket = $this->open($server, 'psr&fault='.$fault.'&phase='.$phase, ['taskIds' => [TaskFixture::ID]]);
        if ($phase !== 'opening') { self::assertSame('notifications/subscriptions/acknowledged', $this->next($socket)['method']); }
        if ($phase === 'update') {
            self::assertSame('working', $this->next($socket)['params']['status']);
            touch($this->directory.'/arm-fault'); $this->worker('failed');
        }
        self::assertSame([], $this->drain($socket), 'No new protected data or successful completion');
        self::assertSame(0, (int) $this->connection->fetchOne($this->sql('SELECT COUNT(*) FROM fight_mcp_subscriptions WHERE closed_at = 0')));
        $native = json_decode(file_get_contents(glob($this->directory.'/closed-*.json')[0]), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($native['faultTriggered']); self::assertTrue($native['guardReleased']); self::assertNotEmpty($native['diagnostics']);
        self::assertSame($phase === 'update' ? 'failed' : 'working', $fixture->get()->get('state')->get('status'));
        self::assertNull($fixture->cancellation()->get('cancellation')); $this->assertPrivateStorage();
    }

    #[DataProvider('databases')]
    public function test_that_tracking_storage_failure_closes_without_success_and_independent_current_state_survives(string $backend, bool $configured): void
    {
        $this->database($backend, $configured);
        $fixture = new TaskFixture($this->connection, $this->tables->task); $fixture->create();
        $server = $this->server(); $poll = $this->server();
        $socket = $this->open($server, 'psr', ['taskIds' => [TaskFixture::ID]]);
        self::assertSame('notifications/subscriptions/acknowledged', $this->next($socket)['method']);
        self::assertSame('working', $this->next($socket)['params']['status']);
        $this->connection->executeStatement($this->sql('UPDATE fight_mcp_subscription_state SET version = 77'));
        $this->worker('completed');
        self::assertSame([], $this->drain($socket), 'Storage failure cannot become a change frame or graceful completion.');
        self::assertSame('completed', $fixture->get()->get('state')->get('status'), 'Independent Task Query does not depend on tracking health.');
        self::assertNull($fixture->cancellation()->get('cancellation'));
        // Repair is an explicit fixture/operator action, never runtime fallback or migration.
        // A fresh HTTP fixture explicitly validates all of its composed schemas before serving.
        $this->connection->executeStatement($this->sql('UPDATE fight_mcp_subscription_state SET version = 1'));
        $current = $this->rpc($poll, TaskEndpoint::request('tasks/get', ['taskId' => TaskFixture::ID]))['result'];
        self::assertSame('completed', $current['status']);
        $this->assertPrivateStorage();
    }

    public static function sourceRaces(): iterable
    {
        foreach (['signal', 'close', 'cleanup'] as $fault) {
            foreach (['opening', 'baseline', 'update'] as $phase) {
                yield 'sqlite-'.$fault.'-'.$phase.'-configured' => ['sqlite', $fault, $phase, true];
            }
        }
    }

    public static function compositions(): iterable
    {
        foreach (self::databases() as $label => [$backend, $configured]) {
            foreach (['psr', 'symfony', 'laravel', 'codeigniter', 'slim', 'yii'] as $composition) { yield $label.'-'.$composition => [$backend, $composition, $configured]; }
        }
    }

    public static function outcomes(): iterable
    {
        foreach (self::databases() as $label => [$backend, $configured]) {
            foreach (['failed', 'cancelled'] as $outcome) { yield $label.'-'.$outcome => [$backend, $outcome, $configured]; }
        }
    }

    public static function databases(): iterable
    {
        yield 'sqlite-configured' => ['sqlite', true];
    }

    private function database(string $backend, bool $configured): void
    {
        $this->tables = $configured ? LifecycleTables::configured() : new LifecycleTables();
        $this->directory = sys_get_temp_dir().'/fight-task-live-'.bin2hex(random_bytes(8)); self::assertTrue(mkdir($this->directory, 0700));
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'path' => $this->directory.'/db.sqlite']; }
        else { $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN'); self::assertIsString($dsn, 'Real disposable database required'); $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn); }
        $this->connection = DriverManager::getConnection($params); if ($backend === 'sqlite') { $this->connection->executeStatement('PRAGMA busy_timeout = 3000'); }
        $this->dropTables();
        (new DbalTaskSchema($this->tables->task))->install($this->connection);
        (new DbalSubscriptionSchema($this->tables->subscription))->install($this->connection);
        (new DbalNoticeSchema($this->tables->notice))->install($this->connection);
        if ($configured) {
            (new DbalTaskSchema())->install($this->connection); (new DbalSubscriptionSchema())->install($this->connection); (new DbalNoticeSchema())->install($this->connection);
            (new TaskFixture($this->connection))->create(1000, 99);
            (new NoticeFixture($this->connection))->record(['id' => 'default-only', 'type' => 'mcp.tools.list_changed']);
            (new DbalSubscriptionStore($this->connection))->open(str_repeat('d', 32), str_repeat('e', 32), ['subscriber' => 'default-only', 'selection' => ['scopes' => ['scope'], 'filters' => []], 'requested' => [], 'accepted' => []], 100, 105);
            foreach (['fight_task_state', 'fight_notice_state', 'fight_mcp_subscription_state'] as $table) { $this->connection->executeStatement('UPDATE '.$table.' SET version = 77'); }
            $this->defaultSnapshot = $this->defaultRows();
        }
        file_put_contents($this->directory.'/config.json', json_encode(['params' => $params, 'tables' => $this->tables->configuration], JSON_THROW_ON_ERROR));
        $this->trace['backend'] = $backend; $this->trace['tables'] = $this->tables->configuration;
        $this->trace['default_before'] = $this->defaultSnapshot;
    }

    private function server(): string
    {
        $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error); self::assertIsResource($reservation, $error); $address = stream_socket_get_name($reservation, false); fclose($reservation);
        $process = new Process([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'zlib.output_compression=0', '-d', 'display_errors=0', '-S', $address, 'tests/Fixture/Mcp/task-live-server.php'], dirname(__DIR__, 2), ['FIGHT_TASK_LIVE_DIRECTORY' => $this->directory]); $process->start(); $this->servers[] = $process;
        for ($i = 0; $i < 300; ++$i) { $probe = @stream_socket_client('tcp://'.$address, $errno, $error, 0.05); if (is_resource($probe)) { fclose($probe); return $address; } usleep(10000); }
        self::fail($process->getErrorOutput());
    }

    private function send(string $address, ServerRequest $request, string $query = '', string $label = 'rpc')
    {
        $socket = stream_socket_client('tcp://'.$address, $errno, $error, 5); self::assertIsResource($socket, $error); stream_set_timeout($socket, 10);
        $requestKey = 'request-'.(count($this->trace['requests'] ?? []) + 1);
        $this->socketRequests[(int) $socket] = $requestKey;
        $this->trace['requests'][$requestKey] = ['label' => $label, 'address' => $address, 'query' => $query, 'body' => (string) $request->getBody(), 'connected' => TaskLiveDiagnostics::clock()];
        $request = $request->withHeader('X-Fight-Test-Request', $requestKey);
        $body = (string) $request->getBody(); $wire = "POST /mcp?$query HTTP/1.1\r\nHost: $address\r\nConnection: close\r\nContent-Length: ".strlen($body)."\r\n";
        foreach ($request->getHeaders() as $name => $values) { foreach ($values as $value) { $wire .= "$name: $value\r\n"; } } fwrite($socket, $wire."\r\n".$body);
        $headers = ''; while (($line = fgets($socket)) !== false && $line !== "\r\n") { $headers .= $line; } $this->trace['headers'][] = $headers; $this->trace['requests'][$requestKey]['headers'] = $headers; $this->socketBoundary($socket, 'headers-read'); return [$socket, $headers];
    }

    private function open(string $address, string $composition, array $filters, int|string $id = 7, string $label = 'stream')
    {
        [$socket, $headers] = $this->send($address, TaskLiveFixture::request($filters, $id), 'composition='.$composition, $label); self::assertStringContainsString('text/event-stream', $headers); return $socket;
    }

    private function rpc(string $address, ServerRequest $request): array
    {
        [$socket, $headers] = $this->send($address, $request); self::assertStringContainsString('application/json', $headers); $json = stream_get_contents($socket); $this->recordRaw($socket, $json); $this->socketBoundary($socket, 'rpc-read-end'); self::assertFalse(stream_get_meta_data($socket)['timed_out']); fclose($socket);
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR); $this->trace['rpc'][] = $data; return $data;
    }

    private function next($socket): array
    {
        while (($line = fgets($socket)) !== false) { $this->recordRaw($socket, $line); if (str_starts_with($line, 'data: ')) { $data = json_decode(substr($line, 6), true, flags: JSON_THROW_ON_ERROR); $this->trace['frames'][] = ['request' => $this->socketRequests[(int) $socket] ?? 'unregistered', ...TaskLiveDiagnostics::clock(), 'data' => $data]; return $data; } }
        $this->socketBoundary($socket, 'next-read-false');
        if (isset($this->connection)) { $this->trace['failedReadRows'] = TaskLiveDiagnostics::rows($this->connection, $this->tables); }
        self::fail('Expected live frame before closure');
    }

    private function untilStatus($socket, string $status): array
    {
        for ($i = 0; $i < 8; ++$i) { $frame = $this->next($socket); if (($frame['params']['status'] ?? null) === $status) { return $frame['params']; } } self::fail('Expected current state');
    }

    private function drain($socket): array
    {
        $frames = []; while (($line = fgets($socket)) !== false) { $this->recordRaw($socket, $line); if (str_starts_with($line, 'data: ')) { $frames[] = json_decode(substr($line, 6), true, flags: JSON_THROW_ON_ERROR); } } $this->socketBoundary($socket, 'drain-read-false'); self::assertFalse(stream_get_meta_data($socket)['timed_out']); fclose($socket); $this->trace['tails'][] = $frames; return $frames;
    }

    private function disconnect($socket): void { $this->socketBoundary($socket, 'client-disconnect'); stream_socket_shutdown($socket, STREAM_SHUT_RDWR); fclose($socket); }

    private function recordRaw($socket, string $bytes): void
    {
        $requestKey = $this->socketRequests[(int) $socket] ?? 'unregistered';
        $requestTrace = &$this->trace['requests'][$requestKey];
        $requestTrace['rawBytes'] = ($requestTrace['rawBytes'] ?? 0) + strlen($bytes);
        $requestTrace['raw'] = ($requestTrace['raw'] ?? '').substr($bytes, 0, max(0, 131072 - strlen($requestTrace['raw'] ?? '')));
        $requestTrace['lastRead'] = TaskLiveDiagnostics::clock();
        $requestTrace['rawTruncated'] = $requestTrace['rawBytes'] > 131072;
    }

    private function socketBoundary($socket, string $boundary): void
    {
        $requestKey = $this->socketRequests[(int) $socket] ?? 'unregistered';
        $this->trace['requests'][$requestKey]['boundaries'][] = ['boundary' => $boundary, ...TaskLiveDiagnostics::clock(), 'eof' => feof($socket), 'metadata' => stream_get_meta_data($socket)];
    }
    private function assertNewer(array $old, array $new): void { self::assertSame($old['_meta'][McpTasks::REVISION]['scope'], $new['_meta'][McpTasks::REVISION]['scope']); self::assertGreaterThan($old['_meta'][McpTasks::REVISION]['value'], $new['_meta'][McpTasks::REVISION]['value']); }
    private function worker(string $mode): array
    {
        $this->trace['workerBoundaries'][] = ['mode' => $mode, 'boundary' => 'enter', ...TaskLiveDiagnostics::clock()];
        $process = new Process([PHP_BINARY, 'tests/TestCase/Mcp/Task/http-worker.php', $this->directory.'/config.json', $mode], dirname(__DIR__, 2), timeout: 10);
        try { $process->mustRun(); } finally { $this->trace['workerBoundaries'][] = ['mode' => $mode, 'boundary' => 'leave', ...TaskLiveDiagnostics::clock(), 'exit' => $process->getExitCode(), 'output' => $process->getOutput(), 'error' => $process->getErrorOutput()]; } $data = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR); self::assertNotSame(getmypid(), $data['pid']); $this->trace['suppliers'][] = $data; return $data;
    }

    private function assertPrivateStorage(): void
    {
        $rows = $this->connection->fetchAllAssociative($this->sql('SELECT * FROM fight_mcp_subscriptions')); $this->trace['tracking'] = $rows;
        $encoded = json_encode($rows, JSON_THROW_ON_ERROR);
        foreach (['PRIVATE ANSWER SENTINEL', 'inputRequests', 'structuredContent', 'taskRevision', 'creation_digest'] as $private) { self::assertStringNotContainsString($private, $encoded); }
        $notices = $this->connection->fetchAllAssociative($this->sql('SELECT * FROM fight_notices'));
        $this->trace['notices'] = $notices;
        self::assertLessThanOrEqual(1, count($notices));
        foreach ($notices as $notice) { self::assertSame('mcp.tools.list_changed', json_decode($notice['payload'], true, flags: JSON_THROW_ON_ERROR)['type']); }
        foreach (['PRIVATE ANSWER SENTINEL', 'inputRequests', 'structuredContent', 'taskRevision', 'creation_digest'] as $private) { self::assertStringNotContainsString($private, json_encode($notices, JSON_THROW_ON_ERROR)); }
        foreach ($this->trace['frames'] ?? [] as $frame) { self::assertStringNotContainsString('PRIVATE ANSWER SENTINEL', json_encode($frame)); }
    }

    protected function tearDown(): void
    {
        foreach ($this->servers as $process) { $process->stop(); $this->trace['servers'][] = ['exit' => $process->getExitCode(), 'log' => $process->getErrorOutput(), 'output' => $process->getOutput()]; } $this->servers = [];
        $defaultsUnchanged = true;
        if (isset($this->connection)) {
            $this->trace['finalRows'] = TaskLiveDiagnostics::rows($this->connection, $this->tables);
            if ($this->defaultSnapshot !== []) {
                $this->trace['default_after'] = $this->defaultRows();
                $defaultsUnchanged = $this->defaultSnapshot === $this->trace['default_after'];
            }
            $this->dropTables();
            $this->trace['remaining_owned_tables'] = $this->ownedTableNames();
            $this->connection->close();
        }
        if (isset($this->directory)) {
            foreach (glob($this->directory.'/closed-*.json') as $file) { $this->trace['native'][] = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR); }
            // Native build/exec does not forward arbitrary host env vars. Keep default capture on the /app bind mount.
            $destination = getenv('FIGHT_TASK_LIVE_EVIDENCE_DIR');
            if ($destination === false) { $destination = dirname(__DIR__, 2).'/var/reports/task-live'; }
            if (!is_dir($destination) && !mkdir($destination, 0700, true) && !is_dir($destination)) { throw new RuntimeException('Cannot create private Task-live evidence directory'); }
            file_put_contents($destination.'/'.str_replace([' ', '"', '/'], '-', $this->nameWithDataSet()).'-'.bin2hex(random_bytes(4)).'.json', json_encode($this->trace, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $this->removeTemporaryDirectory($this->directory, 'fight-task-live-');
        }
        $this->trace = []; $this->socketRequests = []; $this->defaultSnapshot = [];
        parent::tearDown();
        self::assertTrue($defaultsUnchanged, 'Configured live suppliers must not read or mutate populated default counterparts.');
    }

    private function sql(string $sql): string
    {
        return $this->tables->sql($this->connection, $sql);
    }

    private function defaultRows(): array
    {
        $rows = [];
        foreach (array_keys($this->tables->names()) as $name) { $rows[$name] = $this->connection->fetchAllAssociative('SELECT * FROM '.$name); }
        return $rows;
    }

    private function ownedTableNames(): array
    {
        $names = [];
        foreach ([$this->tables, new LifecycleTables()] as $selection) {
            foreach (array_reverse($selection->names()) as $table) { $names[$table->name()] = $table; }
        }
        $existing = array_map(static fn ($name) => trim($name, '`"'), $this->connection->createSchemaManager()->listTableNames());
        return array_intersect_key($names, array_flip($existing));
    }

    private function dropTables(): void
    {
        foreach ($this->ownedTableNames() as $table) { $this->connection->executeStatement('DROP TABLE '.$table->quoted($this->connection->getDatabasePlatform())); }
    }
}
