<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeSchema;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTables;
use Fight\Common\Application\Mcp\Notice\Exception\CommittedNoticeDispatchFailed;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeReadFailed;
use Fight\Common\Application\Mcp\Notice\Service\NoticeContinuationProtector;
use Fight\Common\Domain\Mcp\Notice\Event\NoticeRecorded;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Mcp\Notice\Query\RecoverNotices;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeRecoveryFixture;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeRemovalFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class DbalNoticeRecoveryIntegrationTest extends UnitTestCase
{
    private array $connections = [];
    private array $directories = [];

    #[DataProvider('databases')]
    public function test_that_failed_postcommit_publication_and_multibatch_query_http_recovery_preserve_authorized_committed_order(string $backend, DbalNoticeTables $tables): void
    {
        $db = $this->database($backend, $tables); $writer = new NoticeFixture($db, $tables); $fixture = new NoticeRecoveryFixture($db, tables: $tables); $events = 0;
        $writer->eventDispatcher->addHandler(ClassName::underscore(NoticeRecorded::class), static function () use (&$events): void { ++$events; throw new RuntimeException('PRIVATE publication sentinel'); });
        for ($i = 1; $i <= 105; ++$i) {
            $changes = ['id' => 'notice-'.$i, 'producer' => $i % 2 ? 'producer' : 'producer-2'];
            if ($i % 3 === 0) { $changes['relationships'] = ['subject' => null, 'recipients' => $i % 2 ? [] : null, 'author' => null, 'command' => null, 'event' => null, 'action' => null]; }
            try { $writer->record($changes); self::fail('Controlled publisher must fail after commit'); } catch (CommittedNoticeDispatchFailed $error) { self::assertSame('notice-'.$i, $error->event()->toArray()['id']); }
            $writer->record($changes); // Matching retry does not publish again.
        }
        try { $writer->record(['scope' => 'scope-2', 'metadata' => ['app/label' => 'CONCEALED-SENTINEL']]); } catch (CommittedNoticeDispatchFailed) {}
        $state = $fixture->state(); $admissions = $writer->admissions;
        self::assertSame(106, $events);
        $catalog = $fixture->request('tools/list')['body']['result']; self::assertSame(['notices.recover'], array_column($catalog['tools'], 'name'));
        $capabilities = $fixture->request('server/discover')['body']['result']['capabilities'];
        foreach (['subscriptions', 'resources', 'prompts', 'tasks'] as $family) { self::assertArrayNotHasKey($family, $capabilities); }
        self::assertSame(-32601, $fixture->request('subscriptions/poll')['body']['error']['code']); self::assertSame(0, $fixture->admissions);
        $args = ['scopes' => ['scope', 'scope-2'], 'batch_size' => 32, 'scan_limit' => 100]; $ids = []; $statuses = [];
        do {
            $result = $this->equivalent($fixture, $args); $statuses[] = $result['status'];
            foreach ($result['notices'] as $notice) { $ids[] = $notice['id']; self::assertArrayNotHasKey('position', $notice); }
            self::assertStringNotContainsString('CONCEALED-SENTINEL', $fixture->lastResponse);
            self::assertStringNotContainsString('PRIVATE publication sentinel', $fixture->lastResponse);
            $args['continuation'] = $result['continuation'];
        } while ($result['status'] !== 'complete');
        self::assertSame(array_map(static fn ($i) => 'notice-'.$i, range(1, 105)), $ids);
        self::assertSame(['incomplete', 'incomplete', 'incomplete', 'complete'], $statuses);
        $empty = $this->equivalent($fixture, $args); self::assertSame([], $empty['notices']); self::assertSame('complete', $empty['status']);
        self::assertSame($state, $fixture->state()); self::assertSame($admissions, $writer->admissions); self::assertSame(106, $events);
        self::assertFalse($db->isTransactionActive());
        try { $writer->record(['id' => 'new-after-boundary']); } catch (CommittedNoticeDispatchFailed) {}
        $next = $this->equivalent($fixture, $args); self::assertSame(['new-after-boundary'], array_column($next['notices'], 'id')); self::assertSame('complete', $next['status']);
        self::assertSame([], $fixture->diagnostics);
    }

    #[DataProvider('databases')]
    public function test_that_current_grants_context_integrity_and_hidden_loss_never_create_false_completion_or_leakage(string $backend, DbalNoticeTables $tables): void
    {
        $db = $this->database($backend, $tables); $writer = new NoticeRemovalFixture($db, $tables); $fixture = new NoticeRecoveryFixture($db, tables: $tables);
        $writer->recording->record(['id' => 'old']); $writer->recording->record(['scope' => 'scope-2', 'id' => 'newly-permitted', 'metadata' => ['app/label' => 'CONCEALED-SENTINEL']]);
        $args = ['scopes' => ['scope', 'scope-2'], 'batch_size' => 1]; $first = $this->equivalent($fixture, $args); self::assertSame('complete', $first['status']);
        $old = $args + ['continuation' => $first['continuation']];
        foreach ([NoticeRecoveryFixture::access(caller: 'different'), NoticeRecoveryFixture::access(revision: 'new-revision'), NoticeRecoveryFixture::access([]), NoticeRecoveryFixture::access(['scope', 'scope-2'])] as $access) {
            $fixture->access = $access; $this->assertSafeError($fixture->call($old));
        }
        $fixture->access = NoticeRecoveryFixture::access(['scope', 'scope-2'], revision: 'new-revision');
        $restart = $this->equivalent($fixture, $args); self::assertSame('old', $restart['notices'][0]['id']);
        $second = $this->equivalent($fixture, $args + ['continuation' => $restart['continuation']]); self::assertSame('newly-permitted', $second['notices'][0]['id']); self::assertSame('complete', $second['status']);
        $fixture->access = NoticeRecoveryFixture::access();
        foreach ([['scopes' => ['scope']], ['filters' => ['topic' => 'catalog/tools']], ['batch_size' => 2], ['scan_limit' => 1]] as $change) { $this->assertSafeError($fixture->call(array_replace($old, $change))); }
        $tampered = $old; $tampered['continuation'][100] = $tampered['continuation'][100] === 'a' ? 'b' : 'a'; $this->assertSafeError($fixture->call($tampered));
        $plain = NoticeRecoveryFixture::codec()->open($first['continuation']);
        self::assertStringNotContainsString('CONCEALED-SENTINEL', $plain); self::assertStringNotContainsString('scope-2', $plain);
        $decoded = json_decode($plain, true); $decoded['expires'] = time() - 1;
        $this->assertSafeError($fixture->call(array_replace($old, ['continuation' => NoticeRecoveryFixture::codec()->seal(json_encode($decoded, JSON_THROW_ON_ERROR))])));
        $fixture->access = NoticeRecoveryFixture::access(revision: null, evidence: false);
        $uncertain = $this->equivalent($fixture, $args); self::assertSame('uncertain', $uncertain['status']);
        $writer->remove($writer->selection([NoticeRemovalFixture::target(2, 'newly-permitted', 'scope-2')]));
        $after = $this->equivalent($fixture, $args + ['continuation' => $uncertain['continuation']]);
        self::assertSame('uncertain', $after['status']); self::assertSame([], $after['notices']); self::assertSame(1432, strlen($after['continuation']));
        self::assertStringNotContainsString('CONCEALED-SENTINEL', $fixture->lastResponse);
        // Current positive access with no trustworthy policy epoch remains uncertain even on an empty feed.
        $fixture->access = NoticeRecoveryFixture::access(['scope', 'scope-2'], revision: null);
        self::assertSame('uncertain', $this->equivalent($fixture, ['scopes' => ['scope', 'scope-2']])['status']);
    }

    #[DataProvider('databases')]
    public function test_that_actual_removal_final_row_retirement_and_missing_evidence_are_not_successful_empty_catchup(string $backend, DbalNoticeTables $tables): void
    {
        $db = $this->database($backend, $tables); $writer = new NoticeRemovalFixture($db, $tables); $fixture = new NoticeRecoveryFixture($db, tables: $tables);
        $stateTable = $tables->stateTable()->quoted($db->getDatabasePlatform());
        $writer->recording->record(['id' => 'first']); $writer->recording->record(['id' => 'last']);
        $args = ['scopes' => ['scope'], 'batch_size' => 1]; $first = $this->equivalent($fixture, $args); self::assertSame('incomplete', $first['status']);
        $writer->remove($writer->selection([NoticeRemovalFixture::target(2, 'last')]));
        $args['continuation'] = $first['continuation']; $missing = $this->equivalent($fixture, $args);
        self::assertSame([], $missing['notices']); self::assertSame('incomplete', $missing['status']);
        $writer->remove($writer->selection([NoticeRemovalFixture::target(1, 'first')]));
        self::assertSame('incomplete', $this->equivalent($fixture, $args)['status']);
        self::assertSame('incomplete', $this->equivalent($fixture, ['scopes' => ['scope']])['status']);
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM '.$tables->comparisonTable()->quoted($db->getDatabasePlatform())));
        $db->update($stateTable, ['namespace' => bin2hex(random_bytes(16)), 'lost_through' => 2], ['singleton' => 1]);
        $this->assertSafeError($fixture->call($args));
        self::assertSame('incomplete', $this->equivalent($fixture, ['scopes' => ['scope']])['status']);
        $db->delete($stateTable, ['singleton' => 1]);
        $this->assertSafeError($fixture->call(['scopes' => ['scope']]));
        try { $fixture->queryBus->fetch(new RecoverNotices(NoticeSelection::create(['scope']))); self::fail('Missing evidence must fail'); } catch (NoticeReadFailed $error) { self::assertSame('Retained notices could not be read completely.', $error->getMessage()); }
    }

    #[DataProvider('databases')]
    public function test_that_scans_exact_batches_inputs_availability_guard_and_storage_failures_are_bounded_and_independent(string $backend, DbalNoticeTables $tables): void
    {
        $db = $this->database($backend, $tables); $writer = new NoticeFixture($db, $tables); $fixture = new NoticeRecoveryFixture($db, tables: $tables);
        for ($i = 1; $i <= 101; ++$i) { $writer->record(['id' => 'notice-'.$i, 'topic' => $i === 101 ? 'target' : 'other']); }
        $args = ['scopes' => ['scope'], 'filters' => ['topic' => 'target'], 'batch_size' => 32, 'scan_limit' => 100];
        $scan = $this->equivalent($fixture, $args); self::assertSame([], $scan['notices']); self::assertSame('incomplete', $scan['status']);
        $next = $this->equivalent($fixture, $args + ['continuation' => $scan['continuation']]); self::assertSame(['notice-101'], array_column($next['notices'], 'id')); self::assertSame('complete', $next['status']);
        $selection = ['scopes' => array_map(static fn ($i) => 'scope-'.$i, range(1, 16)), 'continuation' => str_repeat('x', 2048)];
        $this->assertSafeError($fixture->call($selection)); // Exact byte bound reaches authenticated validation; it is not a valid token.
        $before = $fixture->admissions;
        foreach ([['scopes' => array_fill(0, 17, 'scope')], ['continuation' => str_repeat('x', 2049)], ['batch_size' => 33], ['batch_size' => 0], ['scan_limit' => 101], ['scan_limit' => 0], ['caller' => 'SUPER_ADMIN'], ['access' => true], ['filters' => ['body' => 'private']]] as $bad) {
            $response = $fixture->call(array_replace(['scopes' => ['scope']], $bad)); self::assertTrue($response['body']['result']['isError']); self::assertArrayNotHasKey('structuredContent', $response['body']['result']);
        }
        self::assertSame($before, $fixture->admissions);
        $fixture->available = false; $denied = $fixture->call(['scopes' => ['scope']]);
        self::assertSame($denied, $fixture->request('tools/call', ['name' => 'guessed', 'arguments' => (object) ['scopes' => ['scope']]]));
        self::assertSame([], $fixture->request('tools/list')['body']['result']['tools']); self::assertSame($before, $fixture->admissions);
        $fixture->available = true; $fixture->guard = false; self::assertSame(429, $fixture->call(['scopes' => ['scope']])['status']); self::assertSame($before, $fixture->admissions);
        $fixture->guard = true; $state = $fixture->state(); $fixture->access = NoticeRecoveryFixture::access([]);
        $unknown = $this->equivalent($fixture, ['scopes' => ['scope']]); $other = $this->equivalent($fixture, ['scopes' => ['missing']]);
        self::assertSame([], $unknown['notices']); self::assertSame([], $other['notices']); self::assertSame($unknown['status'], $other['status']); self::assertSame($state, $fixture->state());
        $fixture->access = NoticeRecoveryFixture::access(); $db->executeStatement('DROP TABLE '.$tables->comparisonTable()->quoted($db->getDatabasePlatform()));
        $this->assertSafeError($fixture->call(['scopes' => ['scope']]));
    }

    #[DataProvider('databases')]
    public function test_that_actual_encoded_envelope_and_projection_exact_limits_include_escaping_text_metadata_and_one_oversized_notice(string $backend, DbalNoticeTables $tables): void
    {
        $db = $this->database($backend, $tables); $writer = new NoticeFixture($db, $tables);
        $writer->record(['metadata' => ['app/label' => str_repeat('雪"\\/', 20)]]);
        // Fixed-width presentation-only protector isolates exact byte arithmetic from random nonce bytes.
        // Production encryption/tampering is exercised separately and on every ordinary journey above.
        $fixed = new class implements NoticeContinuationProtector { public function seal(string $plaintext): string { return str_repeat('x', 1432); } public function open(string $token): string { throw new RuntimeException('Not used'); } };
        $args = ['scopes' => ['scope']];
        $baseline = new NoticeRecoveryFixture($db, protector: $fixed, tables: $tables); $result = $baseline->call($args)['body']['result'];
        self::assertSame(json_decode($result['content'][0]['text'], true), $result['structuredContent']);
        $encoded = strlen($baseline->lastResponse); $projection = strlen(json_encode($result['structuredContent'], JSON_THROW_ON_ERROR));
        self::assertGreaterThan(2048, $projection);
        $exact = new NoticeRecoveryFixture($db, maxResultBytes: $projection, maxResponseBytes: $encoded, protector: $fixed, tables: $tables);
        self::assertArrayHasKey('result', $exact->call($args)['body']); self::assertSame($encoded, strlen($exact->lastResponse));
        $small = new NoticeRecoveryFixture($db, maxResultBytes: $projection - 1, protector: $fixed, tables: $tables); $this->assertSafeError($small->call($args));
        $small = new NoticeRecoveryFixture($db, maxResponseBytes: $encoded - 1, protector: $fixed, tables: $tables); $this->assertSafeError($small->call($args));
        self::assertStringNotContainsString('continuation', $small->lastResponse);
        $metadata = new NoticeRecoveryFixture($db, maxResponseBytes: $encoded, serverName: str_repeat('"雪\\', 100), protector: $fixed, tables: $tables); $this->assertSafeError($metadata->call($args));
        $largeId = new NoticeRecoveryFixture($db, maxResponseBytes: $encoded, protector: $fixed, tables: $tables); $largeId->requestId = str_repeat('"雪\\', 100); $this->assertSafeError($largeId->call($args));
        $oversized = new NoticeRecoveryFixture($db, maxResultBytes: 2048, tables: $tables); $this->assertSafeError($oversized->call($args));
        $normal = new NoticeRecoveryFixture($db, tables: $tables); self::assertSame('notice-1', $normal->call($args)['body']['result']['structuredContent']['notices'][0]['id']);
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM '.$tables->noticeTable()->quoted($db->getDatabasePlatform())));
    }

    #[DataProvider('races')]
    public function test_that_query_and_http_observe_one_serialized_recording_or_removal_boundary_with_delayed_commits(string $backend, string $mode, DbalNoticeTables $tables): void
    {
        $db = $this->database($backend, $tables); $writer = new NoticeRemovalFixture($db, $tables); $fixture = new NoticeRecoveryFixture($db, tables: $tables);
        $args = ['scopes' => ['scope'], 'batch_size' => 1];
        if ($mode !== 'record-first') {
            $writer->recording->record(); $writer->recording->record(['id' => 'last']);
            $args['continuation'] = $fixture->call($args)['body']['result']['structuredContent']['continuation'];
        }
        $directory = $this->directory(); $config = $directory.'/config.json';
        $selection = $mode === 'record-first' ? null : $writer->selection([NoticeRemovalFixture::target(2, 'last')])->toArray();
        file_put_contents($config, json_encode(['params' => $db->getParams(), 'mode' => match ($mode) { 'record-first' => 'distinct', 'recover-first' => 'record-first-remove', default => 'remove-first-window' }, 'selection' => $selection, 'arguments' => $args, 'tables' => $this->workerTables($tables)], JSON_THROW_ON_ERROR));
        $firstScript = match ($mode) { 'record-first' => 'record-worker.php', 'remove-first' => 'removal-worker.php', default => 'recovery-worker.php' };
        $secondScript = $mode === 'recover-first' ? 'removal-worker.php' : 'recovery-worker.php';
        $first = new Process([PHP_BINARY, __DIR__.'/../../../../../TestCase/Mcp/Notice/'.$firstScript, $config, 'first'], timeout: 25);
        $second = new Process([PHP_BINARY, __DIR__.'/../../../../../TestCase/Mcp/Notice/'.$secondScript, $config, 'second'], timeout: 25);
        try {
            $first->start(); $this->waitFor($directory.($mode === 'record-first' ? '/first-precommit' : '/first-locked'), $first);
            $second->start(); $this->waitFor($directory.'/second-started', $second); usleep(200000);
            self::assertTrue($second->isRunning(), $second->getErrorOutput().$second->getOutput());
            file_put_contents($directory.'/release', 'yes');
            self::assertSame(0, $first->wait(), $first->getErrorOutput()); self::assertSame(0, $second->wait(), $second->getErrorOutput());
            $response = json_decode(($mode === 'recover-first' ? $first : $second)->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $batch = $response['body']['result']['structuredContent'];
            self::assertSame($mode === 'remove-first' ? 'incomplete' : 'complete', $batch['status']);
            self::assertSame($mode === 'remove-first' ? [] : [$mode === 'record-first' ? 'notice-1' : 'last'], array_column($batch['notices'], 'id'));
            $args['continuation'] = $batch['continuation'];
            if ($mode === 'record-first') { $writer->recording->record(['id' => 'notice-2']); self::assertSame(['notice-2'], array_column($this->equivalent($fixture, $args)['notices'], 'id')); }
            elseif ($mode === 'remove-first') { self::assertSame('incomplete', $this->equivalent($fixture, $args)['status']); }
            else { self::assertSame('complete', $this->equivalent($fixture, $args)['status']); self::assertSame([], $this->equivalent($fixture, $args)['notices']); }
        } finally { file_put_contents($directory.'/release', 'yes'); $first->stop(); $second->stop(); }
    }

    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            yield $backend.'-configured' => [$backend, new DbalNoticeTables('NoticeState', 'Order', 'NoticeRelationships', 'NoticeComparisons')];
        }
    }
    public static function races(): iterable { foreach (self::databases() as $name => [$backend, $tables]) { foreach (['record-first', 'remove-first', 'recover-first'] as $mode) { yield $name.'-'.$mode => [$backend, $mode, $tables]; } } }
    protected function tearDown(): void { foreach ($this->connections as $db) { $db->close(); } foreach ($this->directories as $directory) { $this->removeTemporaryDirectory($directory, 'fight-notice-recovery-'); } parent::tearDown(); }
    private function directory(): string { $path = sys_get_temp_dir().'/fight-notice-recovery-'.bin2hex(random_bytes(8)); self::assertTrue(mkdir($path, 0700)); $this->directories[] = $path; return $path; }
    private function database(string $backend, DbalNoticeTables $tables): Connection
    {
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'path' => $this->directory().'/notices.sqlite']; }
        else { $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN'); self::assertIsString($dsn, 'Actual disposable database required'); $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn); }
        $db = DriverManager::getConnection($params); $this->connections[] = $db;
        if ($backend === 'sqlite') { $db->executeStatement('PRAGMA foreign_keys = ON'); $db->executeStatement('PRAGMA busy_timeout = 10000'); }
        // Drop children from every journey selection before shared default parents.
        $names = ['IsolationComparisons', 'IsolationRelations', 'PartialComparisons', 'PartialRelations'];
        foreach (self::databases() as [, $selection]) {
            foreach ([$selection->comparisonTable(), $selection->relationshipTable()] as $table) { $names[] = $table->name(); }
        }
        $names = [...$names, 'Select', 'IsolationState', 'PartialState'];
        foreach (self::databases() as [, $selection]) {
            foreach ([$selection->noticeTable(), $selection->stateTable()] as $table) { $names[] = $table->name(); }
        }
        foreach (array_unique($names) as $name) { $db->executeStatement('DROP TABLE IF EXISTS '.$db->getDatabasePlatform()->quoteSingleIdentifier($name)); }
        (new DbalNoticeSchema($tables))->install($db); return $db;
    }
    private function workerTables(DbalNoticeTables $tables): array
    {
        return ['stateTable' => $tables->stateTable()->name(), 'noticeTable' => $tables->noticeTable()->name(), 'relationshipTable' => $tables->relationshipTable()->name(), 'comparisonTable' => $tables->comparisonTable()->name()];
    }
    private function equivalent(NoticeRecoveryFixture $fixture, array $args): array
    {
        $query = $fixture->queryBus->fetch(new RecoverNotices(NoticeSelection::create($args['scopes'], $args['filters'] ?? []), $args['continuation'] ?? null, $args['batch_size'] ?? 32, $args['scan_limit'] ?? 100));
        $http = $fixture->call($args); self::assertSame(200, $http['status']); self::assertArrayHasKey('result', $http['body'], $fixture->lastResponse);
        $actual = $http['body']['result']['structuredContent']; $expected = $query->toArray();
        $left = json_decode(NoticeRecoveryFixture::codec()->open($expected['continuation']), true); $right = json_decode(NoticeRecoveryFixture::codec()->open($actual['continuation']), true); unset($left['expires'], $right['expires']); self::assertSame($left, $right);
        unset($expected['continuation'], $actual['continuation']); self::assertSame($expected, $actual);
        return $http['body']['result']['structuredContent'];
    }
    private function assertSafeError(array $response): void { self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $response['body']['error']); self::assertArrayNotHasKey('result', $response['body']); }
    private function waitFor(string $path, Process $process): void { $deadline = microtime(true) + 10; while (!file_exists($path)) { if (!$process->isRunning() || microtime(true) > $deadline) { self::fail('Barrier unavailable '.$process->getOutput().$process->getErrorOutput()); } usleep(10000); } }
}
