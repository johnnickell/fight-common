<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Subscription\DbalSubscriptionSchema;
use Fight\Common\Adapter\Mcp\Subscription\DbalSubscriptionStore;
use Fight\Common\Adapter\Notice\Dbal\DbalNoticeSchema;
use Fight\Test\Common\TestCase\Mcp\SubscriptionFixture;
use Fight\Test\Common\TestCase\Notice\NoticeRecoveryFixture;
use Fight\Test\Common\TestCase\Notice\NoticeRemovalFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class McpSubscriptionJourneyTest extends TestCase
{
    private string $directory;
    private Connection $db;
    private array $processes = [];
    private array $evidence = [];

    #[DataProvider('databases')]
    public function test_that_real_shared_database_live_streams_reused_ids_revocation_failure_and_polling_are_isolated(string $backend): void
    {
        $this->setupDatabase($backend);
        $a = $this->server('scope'); $b = $this->server('scope-2');
        $left = $this->open($a, ['toolsListChanged' => true]);
        $right = $this->open($b, ['toolsListChanged' => true, 'resourceSubscriptions' => ['file:///allowed']]);
        $ackA = $this->nextFrame($left); $ackB = $this->nextFrame($right);
        self::assertSame('notifications/subscriptions/acknowledged', $ackA['method']);
        self::assertSame('notifications/subscriptions/acknowledged', $ackB['method']);
        self::assertSame(7, $ackA['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
        self::assertSame(7, $ackB['params']['_meta']['io.modelcontextprotocol/subscriptionId']);
        $rows = $this->db->fetchAllAssociative('SELECT * FROM fight_mcp_subscriptions');
        self::assertCount(2, $rows); self::assertNotSame($rows[0]['registration_id'], $rows[1]['registration_id']);
        self::assertNotSame($rows[0]['owner'], $rows[1]['owner']);
        $this->worker(['operation' => 'record', 'notices' => [
            ['id' => 'a', 'scope' => 'scope', 'type' => 'mcp.tools.list_changed', 'metadata' => ['app/hidden' => 'CONCEALED-A']],
            ['id' => 'b-tool', 'scope' => 'scope-2', 'type' => 'mcp.tools.list_changed'],
            ['id' => 'b', 'scope' => 'scope-2', 'type' => 'mcp.resources.updated', 'metadata' => ['mcp/uri' => 'file:///allowed', 'app/hidden' => 'CONCEALED-B']],
            ['id' => 'hidden-resource', 'scope' => 'scope-2', 'type' => 'mcp.resources.updated', 'metadata' => ['mcp/uri' => 'file:///hidden']]
        ]]);
        $updateA = $this->nextFrame($left); $toolB = $this->nextFrame($right); $updateB = $this->nextFrame($right);
        self::assertSame('notifications/tools/list_changed', $updateA['method']);
        self::assertSame('notifications/tools/list_changed', $toolB['method']);
        self::assertSame('notifications/resources/updated', $updateB['method']);
        self::assertSame('file:///allowed', $updateB['params']['uri']);
        self::assertSame(2, (int) $this->db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions WHERE closed_at = 0'));
        touch($this->directory.'/revoke-scope');
        $this->worker(['operation' => 'record', 'notices' => [['id' => 'after-revoke', 'type' => 'mcp.tools.list_changed']]]);
        // A deliberately paused reader resumes without conferring a permission lease on queued source rows.
        usleep(150000);
        $tailA = $this->drain($left); $tailB = $this->drain($right);
        self::assertCount(1, $tailA); self::assertCount(1, $tailB);
        self::assertSame('complete', $tailA[0]['result']['resultType']);
        self::assertSame('complete', $tailB[0]['result']['resultType']);
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions WHERE closed_at = 0'));
        $this->evidence['wire'] = compact('ackA', 'ackB', 'updateA', 'toolB', 'updateB', 'tailA', 'tailB');
        self::assertStringNotContainsString('CONCEALED', json_encode($this->evidence['wire']));
        unlink($this->directory.'/revoke-scope');
        $failed = $this->open($a, ['toolsListChanged' => true]); self::assertSame('notifications/subscriptions/acknowledged', $this->nextFrame($failed)['method']);
        touch($this->directory.'/source-failure');
        $this->worker(['operation' => 'record', 'notices' => [['id' => 'retained-after-failure', 'type' => 'mcp.tools.list_changed']]]);
        self::assertSame([], $this->drain($failed), 'Source failure must not masquerade as graceful completion');
        unlink($this->directory.'/source-failure');
        $poll = new NoticeRecoveryFixture($this->db);
        $recovered = $poll->call(['scopes' => ['scope']]);
        self::assertSame(200, $recovered['status']);
        $notices = $recovered['body']['result']['structuredContent']['notices'];
        self::assertSame(['a', 'after-revoke', 'retained-after-failure'], array_column($notices, 'id'));
        self::assertSame('complete', $recovered['body']['result']['structuredContent']['status']);
        $this->evidence['polling'] = $recovered;
        $this->evidence['storage'] = $this->db->fetchAllAssociative('SELECT * FROM fight_mcp_subscriptions');
        self::assertFalse($this->db->isTransactionActive());
    }

    #[DataProvider('compositions')]
    public function test_that_supported_native_compositions_emit_before_close_and_idle_disconnect_is_observed(string $composition): void
    {
        $this->setupDatabase('sqlite'); $server = $this->server('scope');
        $socket = $this->open($server, ['toolsListChanged' => true], $composition);
        $start = microtime(true); $ack = $this->nextFrame($socket);
        self::assertSame('notifications/subscriptions/acknowledged', $ack['method']);
        self::assertSame(0, (int) $this->db->fetchOne('SELECT closed_at FROM fight_mcp_subscriptions'));
        $this->worker(['operation' => 'record', 'notices' => [['id' => 'native', 'type' => 'mcp.tools.list_changed']]]);
        self::assertSame('notifications/tools/list_changed', $this->nextFrame($socket)['method']);
        self::assertLessThan(2.0, microtime(true) - $start);
        stream_socket_shutdown($socket, STREAM_SHUT_RDWR); fclose($socket);
        $deadline = microtime(true) + 4;
        do { $closed = (int) $this->db->fetchOne('SELECT closed_at FROM fight_mcp_subscriptions'); if ($closed !== 0) { break; } usleep(10000); } while (microtime(true) < $deadline);
        self::assertGreaterThan(0, $closed);
        $this->evidence['composition'] = $composition;
        $this->evidence['disconnect_observed_seconds'] = microtime(true) - $start;
        $socket = $this->open($server, [], $composition.'&buffered=1');
        self::assertSame([], $this->drain($socket));
        $this->evidence['runtime'] = json_decode(file_get_contents($this->directory.'/closed-scope.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('cli-server', $this->evidence['runtime']['sapi']);
        self::assertSame('0', $this->evidence['runtime']['buffering']);
        self::assertSame('0', $this->evidence['runtime']['compression']);
    }

    #[DataProvider('databases')]
    public function test_that_abrupt_death_becomes_stale_and_cross_process_owner_updates_cannot_revive_closed_or_expired_rows(string $backend): void
    {
        $this->setupDatabase($backend); $server = $this->server('scope');
        $socket = $this->open($server, ['toolsListChanged' => true], 'psr&abrupt=1');
        self::assertSame('notifications/subscriptions/acknowledged', $this->nextFrame($socket)['method']);
        self::assertSame([], $this->drain($socket));
        $row = $this->db->fetchAssociative('SELECT * FROM fight_mcp_subscriptions');
        self::assertSame(0, (int) $row['closed_at']);
        $this->worker(['operation' => 'close', 'arguments' => [$row['registration_id'], str_repeat('f', 32), time()]]);
        self::assertSame(0, (int) $this->db->fetchOne('SELECT closed_at FROM fight_mcp_subscriptions'));
        $deadline = microtime(true) + 7;
        while (time() < (int) $row['expires_at'] && microtime(true) < $deadline) { usleep(50000); }
        self::assertLessThanOrEqual(time(), (int) $row['expires_at']);
        $exit = $this->worker(['operation' => 'heartbeat', 'arguments' => [$row['registration_id'], $row['owner'], time(), time() + 5]], false);
        self::assertNotSame(0, $exit);
        self::assertSame($row['expires_at'], $this->db->fetchOne('SELECT expires_at FROM fight_mcp_subscriptions'));
        $this->evidence['abandoned'] = $row;
        self::assertSame(1, (new DbalSubscriptionStore($this->db))->cleanup(time(), 1));
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions'));
    }

    #[DataProvider('databases')]
    public function test_that_concurrent_removal_and_over_batch_backlog_end_honestly_and_new_listen_is_not_replay(string $backend): void
    {
        $this->setupDatabase($backend); $server = $this->server('scope');
        foreach (['removal', 'overload'] as $mode) {
            $socket = $this->open($server, ['toolsListChanged' => true]);
            self::assertSame('notifications/subscriptions/acknowledged', $this->nextFrame($socket)['method']);
            touch($this->directory.'/pause-source');
            $deadline = microtime(true) + 1;
            while (!file_exists($this->directory.'/source-paused') && microtime(true) < $deadline) { usleep(1000); clearstatcache(); }
            self::assertFileExists($this->directory.'/source-paused');
            $notices = [];
            for ($i = 0; $i < ($mode === 'removal' ? 1 : 33); ++$i) { $notices[] = ['id' => $mode.$i, 'type' => 'mcp.tools.list_changed']; }
            $this->worker(['operation' => 'record', 'notices' => $notices]);
            if ($mode === 'removal') {
                $position = (int) $this->db->fetchOne('SELECT position FROM fight_notices WHERE notice_id = ?', ['removal0']);
                $remover = new NoticeRemovalFixture($this->db);
                $remover->remove($remover->selection([NoticeRemovalFixture::target($position, 'removal0')]));
            }
            unlink($this->directory.'/pause-source'); unlink($this->directory.'/source-paused');
            self::assertSame([], $this->drain($socket), 'No healthy completion or truncated live success after '.$mode);
        }
        $poll = new NoticeRecoveryFixture($this->db);
        $first = $poll->call(['scopes' => ['scope']])['body']['result']['structuredContent'];
        self::assertCount(32, $first['notices']); self::assertSame('incomplete', $first['status']);
        $second = $poll->call(['scopes' => ['scope'], 'continuation' => $first['continuation']])['body']['result']['structuredContent'];
        self::assertCount(1, $second['notices']); self::assertSame('incomplete', $second['status'], 'Earlier history loss remains qualified');
        $socket = $this->open($server, ['toolsListChanged' => true]);
        self::assertSame('notifications/subscriptions/acknowledged', $this->nextFrame($socket)['method']);
        $tail = $this->drain($socket); self::assertCount(1, $tail); self::assertSame('complete', $tail[0]['result']['resultType']);
        $this->evidence['recovery_after_loss_and_overload'] = [$first, $second];
    }

    public static function databases(): iterable { foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; } }
    public static function compositions(): iterable { foreach (['psr', 'symfony', 'laravel', 'yii', 'slim', 'codeigniter'] as $composition) { yield $composition => [$composition]; } }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) { $this->evidence['servers'][] = ['pid' => $process->getPid(), 'command' => $process->getCommandLine(), 'output' => $process->getErrorOutput()]; $process->stop(1); }
        $this->processes = [];
        if (isset($this->db)) {
            $manager = $this->db->createSchemaManager();
            foreach ([...array_reverse(DbalSubscriptionSchema::TABLES), ...array_reverse(DbalNoticeSchema::TABLES)] as $table) { if (in_array($table, $manager->listTableNames(), true)) { $manager->dropTable($table); } }
            $this->db->close();
        }
        if (isset($this->directory)) {
            $destination = getenv('FIGHT_MCP_SUBSCRIPTION_EVIDENCE_DIR');
            if ($destination !== false) { file_put_contents($destination.'/'.str_replace([' ', '"', '/'], '-', $this->nameWithDataSet()).'.json', json_encode($this->evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)); }
            foreach (glob($this->directory.'/*') as $file) { if (is_file($file)) { unlink($file); } }
            rmdir($this->directory);
        }
        $this->evidence = [];
        parent::tearDown();
    }

    private function setupDatabase(string $backend): void
    {
        $this->directory = sys_get_temp_dir().'/fight-mcp-subscription-'.bin2hex(random_bytes(8)); self::assertTrue(mkdir($this->directory, 0700));
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'path' => $this->directory.'/db.sqlite']; }
        else { $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN'); self::assertIsString($dsn, 'Actual disposable database required'); $params = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql'])->parse($dsn); }
        file_put_contents($this->directory.'/connection.json', json_encode($params, JSON_THROW_ON_ERROR));
        $this->db = DriverManager::getConnection($params);
        if ($backend === 'sqlite') { $this->db->executeStatement('PRAGMA busy_timeout = 3000'); }
        // These DSNs are package-owned disposable fixtures; prior focused suites may leave their namespace behind.
        $manager = $this->db->createSchemaManager();
        foreach ([...array_reverse(DbalSubscriptionSchema::TABLES), ...array_reverse(DbalNoticeSchema::TABLES)] as $table) {
            if (in_array($table, $manager->listTableNames(), true)) { $manager->dropTable($table); }
        }
        (new DbalNoticeSchema())->install($this->db); (new DbalSubscriptionSchema())->install($this->db);
        $this->evidence['backend'] = $backend;
    }

    private function server(string $scope): string
    {
        $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error); self::assertIsResource($reservation, $error);
        $address = stream_socket_get_name($reservation, false); fclose($reservation);
        $process = new Process([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'zlib.output_compression=0', '-d', 'display_errors=0', '-S', $address, 'tests/Fixture/Mcp/subscription-server.php'], dirname(__DIR__, 2), ['MCP_SUBSCRIPTION_DIRECTORY' => $this->directory, 'MCP_SUBSCRIPTION_SCOPE' => $scope]);
        $process->start(); $this->processes[] = $process;
        for ($i = 0; $i < 300; ++$i) { $probe = @stream_socket_client('tcp://'.$address, $errno, $error, 0.05); if (is_resource($probe)) { fclose($probe); return $address; } usleep(10000); }
        self::fail('Server did not start: '.$process->getErrorOutput());
    }

    private function open(string $address, array $filter, string $composition = 'psr')
    {
        $socket = stream_socket_client('tcp://'.$address, $errno, $error, 5); self::assertIsResource($socket, $error); stream_set_timeout($socket, 5);
        $request = SubscriptionFixture::request($filter); $body = (string) $request->getBody();
        $wire = "POST /mcp?composition=$composition HTTP/1.1\r\nHost: $address\r\nConnection: close\r\nContent-Length: ".strlen($body)."\r\n";
        foreach ($request->getHeaders() as $name => $values) { foreach ($values as $value) { $wire .= "$name: $value\r\n"; } }
        fwrite($socket, $wire."\r\n".$body); $headers = '';
        while (($line = fgets($socket)) !== false && $line !== "\r\n") { $headers .= $line; }
        $this->evidence['headers'][] = $headers;
        self::assertStringContainsString(str_contains($composition, 'buffered') ? '500' : '200 OK', $headers);
        if (!str_contains($composition, 'buffered')) { self::assertStringContainsString('text/event-stream', $headers); }
        return $socket;
    }

    private function nextFrame($socket): array
    {
        while (($line = fgets($socket)) !== false) {
            if (str_starts_with($line, 'data: ')) { $this->evidence['frames'][] = ['time' => microtime(true), 'wire' => $line]; return json_decode(substr($line, 6), true, flags: JSON_THROW_ON_ERROR); }
        }
        self::fail('Stream ended or timed out before expected frame');
    }

    private function drain($socket): array
    {
        $frames = [];
        while (($line = fgets($socket)) !== false) { if (str_starts_with($line, 'data: ')) { $frames[] = json_decode(substr($line, 6), true, flags: JSON_THROW_ON_ERROR); } }
        self::assertFalse(stream_get_meta_data($socket)['timed_out']); fclose($socket);
        return $frames;
    }

    private function worker(array $operation, bool $success = true): int
    {
        file_put_contents($this->directory.'/operation.json', json_encode($operation, JSON_THROW_ON_ERROR));
        $process = new Process([PHP_BINARY, 'tests/Fixture/Mcp/subscription-worker.php', $this->directory], dirname(__DIR__, 2)); $process->run();
        $this->evidence['workers'][] = ['exit' => $process->getExitCode(), 'output' => $process->getOutput(), 'error' => $process->getErrorOutput()];
        if ($success) { self::assertSame(0, $process->getExitCode(), $process->getErrorOutput()); }
        return $process->getExitCode();
    }
}
