<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Mcp\Notice\Dbal\DbalNoticeSchema;
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
final class NoticeRecoveryJourneyTest extends UnitTestCase
{
    private array $connections = [];
    private array $directories = [];

    #[DataProvider('databases')]
    public function test_that_failed_postcommit_publication_and_multibatch_query_http_recovery_preserve_authorized_committed_order(string $backend): void
    {
        $db = $this->database($backend); $writer = new NoticeFixture($db); $f = new NoticeRecoveryFixture($db); $events = 0;
        $writer->events->addHandler(ClassName::underscore(NoticeRecorded::class), static function () use (&$events): void { ++$events; throw new RuntimeException('PRIVATE publication sentinel'); });
        for ($i = 1; $i <= 105; ++$i) {
            $changes = ['id' => 'notice-'.$i, 'producer' => $i % 2 ? 'producer' : 'producer-2'];
            if ($i % 3 === 0) { $changes['relationships'] = ['subject' => null, 'recipients' => $i % 2 ? [] : null, 'author' => null, 'command' => null, 'event' => null, 'action' => null]; }
            try { $writer->record($changes); self::fail('Controlled publisher must fail after commit'); } catch (CommittedNoticeDispatchFailed $error) { self::assertSame('notice-'.$i, $error->event()->toArray()['id']); }
            $writer->record($changes); // Matching retry does not publish again.
        }
        try { $writer->record(['scope' => 'scope-2', 'metadata' => ['app/label' => 'CONCEALED-SENTINEL']]); } catch (CommittedNoticeDispatchFailed) {}
        $state = $f->state(); $admissions = $writer->admissions;
        self::assertSame(106, $events);
        $catalog = $f->request('tools/list')['body']['result']; self::assertSame(['notices.recover'], array_column($catalog['tools'], 'name'));
        $capabilities = $f->request('server/discover')['body']['result']['capabilities'];
        foreach (['subscriptions', 'resources', 'prompts', 'tasks'] as $family) { self::assertArrayNotHasKey($family, $capabilities); }
        self::assertSame(-32601, $f->request('subscriptions/poll')['body']['error']['code']); self::assertSame(0, $f->admissions);
        $args = ['scopes' => ['scope', 'scope-2'], 'batch_size' => 32, 'scan_limit' => 100]; $ids = []; $statuses = [];
        do {
            $result = $this->equivalent($f, $args); $statuses[] = $result['status'];
            foreach ($result['notices'] as $notice) { $ids[] = $notice['id']; self::assertArrayNotHasKey('position', $notice); }
            self::assertStringNotContainsString('CONCEALED-SENTINEL', $f->lastResponse);
            self::assertStringNotContainsString('PRIVATE publication sentinel', $f->lastResponse);
            $args['continuation'] = $result['continuation'];
        } while ($result['status'] !== 'complete');
        self::assertSame(array_map(static fn ($i) => 'notice-'.$i, range(1, 105)), $ids);
        self::assertSame(['incomplete', 'incomplete', 'incomplete', 'complete'], $statuses);
        $empty = $this->equivalent($f, $args); self::assertSame([], $empty['notices']); self::assertSame('complete', $empty['status']);
        self::assertSame($state, $f->state()); self::assertSame($admissions, $writer->admissions); self::assertSame(106, $events);
        self::assertFalse($db->isTransactionActive());
        try { $writer->record(['id' => 'new-after-boundary']); } catch (CommittedNoticeDispatchFailed) {}
        $next = $this->equivalent($f, $args); self::assertSame(['new-after-boundary'], array_column($next['notices'], 'id')); self::assertSame('complete', $next['status']);
        self::assertSame([], $f->diagnostics);
    }

    #[DataProvider('databases')]
    public function test_that_current_grants_context_integrity_and_hidden_loss_never_create_false_completion_or_leakage(string $backend): void
    {
        $db = $this->database($backend); $writer = new NoticeRemovalFixture($db); $f = new NoticeRecoveryFixture($db);
        $writer->recording->record(['id' => 'old']); $writer->recording->record(['scope' => 'scope-2', 'id' => 'newly-permitted', 'metadata' => ['app/label' => 'CONCEALED-SENTINEL']]);
        $args = ['scopes' => ['scope', 'scope-2'], 'batch_size' => 1]; $first = $this->equivalent($f, $args); self::assertSame('complete', $first['status']);
        $old = $args + ['continuation' => $first['continuation']];
        foreach ([NoticeRecoveryFixture::access(caller: 'different'), NoticeRecoveryFixture::access(revision: 'new-revision'), NoticeRecoveryFixture::access([]), NoticeRecoveryFixture::access(['scope', 'scope-2'])] as $access) {
            $f->access = $access; $this->assertSafeError($f->call($old));
        }
        $f->access = NoticeRecoveryFixture::access(['scope', 'scope-2'], revision: 'new-revision');
        $restart = $this->equivalent($f, $args); self::assertSame('old', $restart['notices'][0]['id']);
        $second = $this->equivalent($f, $args + ['continuation' => $restart['continuation']]); self::assertSame('newly-permitted', $second['notices'][0]['id']); self::assertSame('complete', $second['status']);
        $f->access = NoticeRecoveryFixture::access();
        foreach ([['scopes' => ['scope']], ['filters' => ['topic' => 'catalog/tools']], ['batch_size' => 2], ['scan_limit' => 1]] as $change) { $this->assertSafeError($f->call(array_replace($old, $change))); }
        $tampered = $old; $tampered['continuation'][100] = $tampered['continuation'][100] === 'a' ? 'b' : 'a'; $this->assertSafeError($f->call($tampered));
        $plain = NoticeRecoveryFixture::codec()->open($first['continuation']);
        self::assertStringNotContainsString('CONCEALED-SENTINEL', $plain); self::assertStringNotContainsString('scope-2', $plain);
        $decoded = json_decode($plain, true); $decoded['expires'] = time() - 1;
        $this->assertSafeError($f->call(array_replace($old, ['continuation' => NoticeRecoveryFixture::codec()->seal(json_encode($decoded, JSON_THROW_ON_ERROR))])));
        $f->access = NoticeRecoveryFixture::access(revision: null, evidence: false);
        $uncertain = $this->equivalent($f, $args); self::assertSame('uncertain', $uncertain['status']);
        $writer->remove($writer->selection([NoticeRemovalFixture::target(2, 'newly-permitted', 'scope-2')]));
        $after = $this->equivalent($f, $args + ['continuation' => $uncertain['continuation']]);
        self::assertSame('uncertain', $after['status']); self::assertSame([], $after['notices']); self::assertSame(1432, strlen($after['continuation']));
        self::assertStringNotContainsString('CONCEALED-SENTINEL', $f->lastResponse);
        // Current positive access with no trustworthy policy epoch remains uncertain even on an empty feed.
        $f->access = NoticeRecoveryFixture::access(['scope', 'scope-2'], revision: null);
        self::assertSame('uncertain', $this->equivalent($f, ['scopes' => ['scope', 'scope-2']])['status']);
    }

    #[DataProvider('databases')]
    public function test_that_actual_removal_final_row_retirement_and_missing_evidence_are_not_successful_empty_catchup(string $backend): void
    {
        $db = $this->database($backend); $writer = new NoticeRemovalFixture($db); $f = new NoticeRecoveryFixture($db);
        $writer->recording->record(['id' => 'first']); $writer->recording->record(['id' => 'last']);
        $args = ['scopes' => ['scope'], 'batch_size' => 1]; $first = $this->equivalent($f, $args); self::assertSame('incomplete', $first['status']);
        $writer->remove($writer->selection([NoticeRemovalFixture::target(2, 'last')]));
        $args['continuation'] = $first['continuation']; $missing = $this->equivalent($f, $args);
        self::assertSame([], $missing['notices']); self::assertSame('incomplete', $missing['status']);
        $writer->remove($writer->selection([NoticeRemovalFixture::target(1, 'first')]));
        self::assertSame('incomplete', $this->equivalent($f, $args)['status']);
        self::assertSame('incomplete', $this->equivalent($f, ['scopes' => ['scope']])['status']);
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM fight_notice_comparisons'));
        $db->update('fight_notice_state', ['namespace' => bin2hex(random_bytes(16)), 'lost_through' => 2], ['singleton' => 1]);
        $this->assertSafeError($f->call($args));
        self::assertSame('incomplete', $this->equivalent($f, ['scopes' => ['scope']])['status']);
        $db->delete('fight_notice_state', ['singleton' => 1]);
        $this->assertSafeError($f->call(['scopes' => ['scope']]));
        try { $f->queries->fetch(new RecoverNotices(NoticeSelection::create(['scope']))); self::fail('Missing evidence must fail'); } catch (NoticeReadFailed $error) { self::assertSame('Retained notices could not be read completely.', $error->getMessage()); }
    }

    #[DataProvider('databases')]
    public function test_that_scans_exact_batches_inputs_availability_guard_and_storage_failures_are_bounded_and_independent(string $backend): void
    {
        $db = $this->database($backend); $writer = new NoticeFixture($db); $f = new NoticeRecoveryFixture($db);
        for ($i = 1; $i <= 101; ++$i) { $writer->record(['id' => 'notice-'.$i, 'topic' => $i === 101 ? 'target' : 'other']); }
        $args = ['scopes' => ['scope'], 'filters' => ['topic' => 'target'], 'batch_size' => 32, 'scan_limit' => 100];
        $scan = $this->equivalent($f, $args); self::assertSame([], $scan['notices']); self::assertSame('incomplete', $scan['status']);
        $next = $this->equivalent($f, $args + ['continuation' => $scan['continuation']]); self::assertSame(['notice-101'], array_column($next['notices'], 'id')); self::assertSame('complete', $next['status']);
        $selection = ['scopes' => array_map(static fn ($i) => 'scope-'.$i, range(1, 16)), 'continuation' => str_repeat('x', 2048)];
        $this->assertSafeError($f->call($selection)); // Exact byte bound reaches authenticated validation; it is not a valid token.
        $before = $f->admissions;
        foreach ([['scopes' => array_fill(0, 17, 'scope')], ['continuation' => str_repeat('x', 2049)], ['batch_size' => 33], ['batch_size' => 0], ['scan_limit' => 101], ['scan_limit' => 0], ['caller' => 'SUPER_ADMIN'], ['access' => true], ['filters' => ['body' => 'private']]] as $bad) {
            $response = $f->call(array_replace(['scopes' => ['scope']], $bad)); self::assertTrue($response['body']['result']['isError']); self::assertArrayNotHasKey('structuredContent', $response['body']['result']);
        }
        self::assertSame($before, $f->admissions);
        $f->available = false; $denied = $f->call(['scopes' => ['scope']]);
        self::assertSame($denied, $f->request('tools/call', ['name' => 'guessed', 'arguments' => (object) ['scopes' => ['scope']]]));
        self::assertSame([], $f->request('tools/list')['body']['result']['tools']); self::assertSame($before, $f->admissions);
        $f->available = true; $f->guard = false; self::assertSame(429, $f->call(['scopes' => ['scope']])['status']); self::assertSame($before, $f->admissions);
        $f->guard = true; $state = $f->state(); $f->access = NoticeRecoveryFixture::access([]);
        $unknown = $this->equivalent($f, ['scopes' => ['scope']]); $other = $this->equivalent($f, ['scopes' => ['missing']]);
        self::assertSame([], $unknown['notices']); self::assertSame([], $other['notices']); self::assertSame($unknown['status'], $other['status']); self::assertSame($state, $f->state());
        $f->access = NoticeRecoveryFixture::access(); $db->executeStatement('DROP TABLE fight_notice_comparisons');
        $this->assertSafeError($f->call(['scopes' => ['scope']]));
    }

    #[DataProvider('databases')]
    public function test_that_actual_encoded_envelope_and_projection_exact_limits_include_escaping_text_metadata_and_one_oversized_notice(string $backend): void
    {
        $db = $this->database($backend); $writer = new NoticeFixture($db);
        $writer->record(['metadata' => ['app/label' => str_repeat('雪"\\/', 20)]]);
        // Fixed-width presentation-only protector isolates exact byte arithmetic from random nonce bytes.
        // Production encryption/tampering is exercised separately and on every ordinary journey above.
        $fixed = new class implements NoticeContinuationProtector { public function seal(string $plaintext): string { return str_repeat('x', 1432); } public function open(string $token): string { throw new RuntimeException('Not used'); } };
        $args = ['scopes' => ['scope']];
        $baseline = new NoticeRecoveryFixture($db, protector: $fixed); $result = $baseline->call($args)['body']['result'];
        self::assertSame(json_decode($result['content'][0]['text'], true), $result['structuredContent']);
        $encoded = strlen($baseline->lastResponse); $projection = strlen(json_encode($result['structuredContent'], JSON_THROW_ON_ERROR));
        self::assertGreaterThan(2048, $projection);
        $exact = new NoticeRecoveryFixture($db, maxResultBytes: $projection, maxResponseBytes: $encoded, protector: $fixed);
        self::assertArrayHasKey('result', $exact->call($args)['body']); self::assertSame($encoded, strlen($exact->lastResponse));
        $small = new NoticeRecoveryFixture($db, maxResultBytes: $projection - 1, protector: $fixed); $this->assertSafeError($small->call($args));
        $small = new NoticeRecoveryFixture($db, maxResponseBytes: $encoded - 1, protector: $fixed); $this->assertSafeError($small->call($args));
        self::assertStringNotContainsString('continuation', $small->lastResponse);
        $metadata = new NoticeRecoveryFixture($db, maxResponseBytes: $encoded, serverName: str_repeat('"雪\\', 100), protector: $fixed); $this->assertSafeError($metadata->call($args));
        $largeId = new NoticeRecoveryFixture($db, maxResponseBytes: $encoded, protector: $fixed); $largeId->requestId = str_repeat('"雪\\', 100); $this->assertSafeError($largeId->call($args));
        $oversized = new NoticeRecoveryFixture($db, maxResultBytes: 2048); $this->assertSafeError($oversized->call($args));
        $normal = new NoticeRecoveryFixture($db); self::assertSame('notice-1', $normal->call($args)['body']['result']['structuredContent']['notices'][0]['id']);
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM fight_notices'));
    }

    #[DataProvider('races')]
    public function test_that_query_and_http_observe_one_serialized_recording_or_removal_boundary_with_delayed_commits(string $backend, string $mode): void
    {
        $db = $this->database($backend); $writer = new NoticeRemovalFixture($db); $f = new NoticeRecoveryFixture($db);
        $args = ['scopes' => ['scope'], 'batch_size' => 1];
        if ($mode !== 'record-first') {
            $writer->recording->record(); $writer->recording->record(['id' => 'last']);
            $args['continuation'] = $f->call($args)['body']['result']['structuredContent']['continuation'];
        }
        $directory = $this->directory(); $config = $directory.'/config.json';
        $selection = $mode === 'record-first' ? null : $writer->selection([NoticeRemovalFixture::target(2, 'last')])->toArray();
        file_put_contents($config, json_encode(['params' => $db->getParams(), 'mode' => match ($mode) { 'record-first' => 'distinct', 'recover-first' => 'record-first-remove', default => 'remove-first-window' }, 'selection' => $selection, 'arguments' => $args], JSON_THROW_ON_ERROR));
        $firstScript = match ($mode) { 'record-first' => 'record-worker.php', 'remove-first' => 'removal-worker.php', default => 'recovery-worker.php' };
        $secondScript = $mode === 'recover-first' ? 'removal-worker.php' : 'recovery-worker.php';
        $first = new Process([PHP_BINARY, __DIR__.'/../TestCase/Mcp/Notice/'.$firstScript, $config, 'first'], timeout: 25);
        $second = new Process([PHP_BINARY, __DIR__.'/../TestCase/Mcp/Notice/'.$secondScript, $config, 'second'], timeout: 25);
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
            if ($mode === 'record-first') { $writer->recording->record(['id' => 'notice-2']); self::assertSame(['notice-2'], array_column($this->equivalent($f, $args)['notices'], 'id')); }
            elseif ($mode === 'remove-first') { self::assertSame('incomplete', $this->equivalent($f, $args)['status']); }
            else { self::assertSame('complete', $this->equivalent($f, $args)['status']); self::assertSame([], $this->equivalent($f, $args)['notices']); }
        } finally { file_put_contents($directory.'/release', 'yes'); $first->stop(); $second->stop(); }
    }

    public static function databases(): iterable { foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; } }
    public static function races(): iterable { foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { foreach (['record-first', 'remove-first', 'recover-first'] as $mode) { yield $backend.'-'.$mode => [$backend, $mode]; } } }
    protected function tearDown(): void { foreach ($this->connections as $db) { $db->close(); } foreach ($this->directories as $directory) { $this->removeTemporaryDirectory($directory, 'fight-notice-recovery-'); } parent::tearDown(); }
    private function directory(): string { $path = sys_get_temp_dir().'/fight-notice-recovery-'.bin2hex(random_bytes(8)); self::assertTrue(mkdir($path, 0700)); $this->directories[] = $path; return $path; }
    private function database(string $backend): Connection
    {
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'path' => $this->directory().'/notices.sqlite']; }
        else { $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN'); self::assertIsString($dsn, 'Actual disposable database required'); $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn); }
        $db = DriverManager::getConnection($params); $this->connections[] = $db;
        if ($backend === 'sqlite') { $db->executeStatement('PRAGMA foreign_keys = ON'); $db->executeStatement('PRAGMA busy_timeout = 10000'); }
        foreach (array_reverse(DbalNoticeSchema::TABLES) as $table) { $db->executeStatement('DROP TABLE IF EXISTS '.$table); }
        (new DbalNoticeSchema())->install($db); return $db;
    }
    private function equivalent(NoticeRecoveryFixture $f, array $args): array
    {
        $query = $f->queries->fetch(new RecoverNotices(NoticeSelection::create($args['scopes'], $args['filters'] ?? []), $args['continuation'] ?? null, $args['batch_size'] ?? 32, $args['scan_limit'] ?? 100));
        $http = $f->call($args); self::assertSame(200, $http['status']); self::assertArrayHasKey('result', $http['body'], $f->lastResponse);
        $actual = $http['body']['result']['structuredContent']; $expected = $query->toArray();
        $left = json_decode(NoticeRecoveryFixture::codec()->open($expected['continuation']), true); $right = json_decode(NoticeRecoveryFixture::codec()->open($actual['continuation']), true); unset($left['expires'], $right['expires']); self::assertSame($left, $right);
        unset($expected['continuation'], $actual['continuation']); self::assertSame($expected, $actual);
        return $http['body']['result']['structuredContent'];
    }
    private function assertSafeError(array $response): void { self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $response['body']['error']); self::assertArrayNotHasKey('result', $response['body']); }
    private function waitFor(string $path, Process $process): void { $deadline = microtime(true) + 10; while (!file_exists($path)) { if (!$process->isRunning() || microtime(true) > $deadline) { self::fail('Barrier unavailable '.$process->getOutput().$process->getErrorOutput()); } usleep(10000); } }
}
