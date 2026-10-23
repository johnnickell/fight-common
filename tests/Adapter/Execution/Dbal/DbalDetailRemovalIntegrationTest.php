<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Execution\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Execution\Dbal\DbalActivityReader;
use Fight\Common\Adapter\Execution\Dbal\DbalDetailReader;
use Fight\Common\Adapter\Execution\Dbal\DbalExecutionReader;
use Fight\Common\Adapter\Execution\Dbal\DbalObservationSchema;
use Fight\Common\Adapter\Execution\Dbal\DbalPhaseReader;
use Fight\Common\Adapter\Execution\Dbal\DbalRecordingTransaction;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Execution\Detail\Exception\DetailRemovalRejected;
use Fight\Common\Application\Execution\Detail\QueryHandler\GetCapturedDetailHandler;
use Fight\Common\Application\Execution\Detail\QueryHandler\ListCapturedMessagesHandler;
use Fight\Common\Application\Execution\Detail\Service\DetailAccess;
use Fight\Common\Application\Execution\Detail\Service\DetailReadAdmission;
use Fight\Common\Application\Execution\Observation\Exception\ObservationConflict;
use Fight\Common\Application\Execution\Observation\Exception\ObservationStorageFailed;
use Fight\Common\Application\Execution\Observation\Exception\ProtectedContentRemoved;
use Fight\Common\Application\Execution\Observation\QueryHandler\ListPhaseActivityHandler;
use Fight\Common\Application\Execution\Phase\QueryHandler\ListExecutionPhasesHandler;
use Fight\Common\Application\Execution\Session\QueryHandler\GetExecutionHandler;
use Fight\Common\Application\Execution\Session\QueryHandler\SummarizeExecutionsHandler;
use Fight\Common\Application\Execution\Session\Service\ExecutionAccess;
use Fight\Common\Application\Execution\Session\Service\ExecutionReadAdmission;
use Fight\Common\Domain\Execution\Detail\CaptureCompleteness;
use Fight\Common\Domain\Execution\Detail\Command\RemoveCapturedDetail;
use Fight\Common\Domain\Execution\Detail\DetailCapture;
use Fight\Common\Domain\Execution\Detail\DetailKind;
use Fight\Common\Domain\Execution\Detail\DetailSelection;
use Fight\Common\Domain\Execution\Detail\OutputSegment;
use Fight\Common\Domain\Execution\Detail\ProtectedContent;
use Fight\Common\Domain\Execution\Detail\Query\GetCapturedDetail;
use Fight\Common\Domain\Execution\Detail\Query\ListCapturedMessages;
use Fight\Common\Domain\Execution\Observation\Event\ObservationRecorded;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Observation\ObservationId;
use Fight\Common\Domain\Execution\Observation\Query\ListPhaseActivity;
use Fight\Common\Domain\Execution\Phase\Query\ListExecutionPhases;
use Fight\Common\Domain\Execution\Session\ExecutionSelection;
use Fight\Common\Domain\Execution\Session\Query\GetExecution;
use Fight\Common\Domain\Execution\Session\Query\SummarizeExecutions;
use Fight\Common\Domain\Execution\Session\SessionReference;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\Execution\ObservationFixture;
use Fight\Test\Common\TestCase\Execution\RecordingFixture;
use Fight\Test\Common\TestCase\Execution\RemovalFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class DbalDetailRemovalIntegrationTest extends UnitTestCase
{
    private array $connections = [];
    private array $directories = [];

    #[DataProvider('databases')]
    public function test_that_public_removal_commits_all_kinds_and_preserves_summary_links_and_truthful_queries(string $backend): void
    {
        $db = $this->database($backend); $store = new DbalRecordingTransaction($db);
        $record = $this->seed($store); $remove = new RemovalFixture($store);
        $events = 0; $record->events()->addHandler(ClassName::underscore(ObservationRecorded::class), static function () use (&$events): void { ++$events; });
        [$queries] = $this->queries($db);
        foreach (DetailKind::cases() as $kind) {
            foreach (CaptureCompleteness::cases() as $completeness) {
                $capture = DetailCapture::create(ObservationFixture::activity()->id(), $kind, 'text/plain', $completeness, $kind === DetailKind::PROCESS_OUTPUT ? OutputSegment::create('stdout', 0, 0) : null);
                $detail = ObservationFixture::detail(['observation_id' => ObservationId::generate()->toString(), 'detail' => $capture->toArray()]);
                $hasBody = in_array($completeness, [CaptureCompleteness::COMPLETE, CaptureCompleteness::PARTIAL], true);
                $record->record($detail, ProtectedContent::create($capture, $hasBody ? 'PROTECTED-ERASE-ME' : null));
                $command = RemovalFixture::command($detail);
                $query = new GetCapturedDetail($command->selection(), $detail->id(), $kind);
                $before = $queries->fetch($query)->toArray();
                self::assertSame($hasBody ? 'retained' : $completeness->value, $before['reference']['availability']);
                $safe = $this->safeState($db); $summary = $this->summary($queries); $eventCount = $events;
                $envelope = CommandMessage::arrayDeserialize(CommandMessage::create($command)->arraySerialize());
                $remove->bus->dispatch($envelope);
                self::assertFalse($db->isTransactionActive());
                $other = $this->connect($db->getParams());
                self::assertSame(0, (int) $other->fetchOne('SELECT COUNT(*) FROM fight_execution_contents WHERE observation_id = ?', [$detail->id()->toString()]), 'Acknowledgment must follow a commit visible on an independent connection.');
                $after = $queries->fetch($query)->toArray();
                self::assertNull($after['chunk']);
                self::assertSame($hasBody ? 'missing' : $completeness->value, $after['reference']['availability']);
                self::assertSame($before['reference']['capture'], $after['reference']['capture']);
                self::assertSame($safe, $this->safeState($db));
                self::assertSame($summary, $this->summary($queries));
                $activity = $queries->fetch(new ListPhaseActivity('project', 'session', 'phase'))->toArray();
                $references = $activity['activities'][0]['details'];
                $reference = array_values(array_filter($references, static fn ($ref) => $ref['observation_id'] === $detail->id()->toString()))[0];
                self::assertSame($after['reference'], $reference);
                self::assertStringNotContainsString('PROTECTED-ERASE-ME', json_encode([$activity, $summary, $after]));
                $remove->bus->execute($command);
                self::assertSame($eventCount, $events, 'Removal and reads publish no events.');
            }
        }
        self::assertSame(40, $remove->admissions);
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM fight_execution_contents'));
    }

    #[DataProvider('databases')]
    public function test_that_read_grants_do_not_authorize_removal_and_repeats_recheck_current_scope(string $backend): void
    {
        $db = $this->database($backend); $store = new DbalRecordingTransaction($db); $record = $this->seed($store);
        $detail = ObservationFixture::detail(); $record->record($detail, ProtectedContent::create($detail->detail(), 'PRIVATE'));
        [$queries, $readAdmission] = $this->queries($db); $remove = new RemovalFixture($store); $command = RemovalFixture::command($detail);
        self::assertNotNull($queries->fetch(new GetCapturedDetail($command->selection(), $detail->id(), DetailKind::MESSAGE))->toArray()['chunk']);
        $remove->allowed = false; $before = $this->allState($db);
        $this->rejects(static fn () => $remove->bus->execute($command));
        self::assertSame($before, $this->allState($db));
        $remove->allowed = true; $readAdmission->access = DetailAccess::create([]);
        self::assertNull($queries->fetch(new GetCapturedDetail($command->selection(), $detail->id(), DetailKind::MESSAGE)));
        $remove->bus->execute($command); // Removal does not require read access.
        $remove->allowed = false; $this->rejects(static fn () => $remove->bus->execute($command));
        $remove->allowed = true;
        foreach (['project_id', 'session_id', 'phase_id', 'producer_id', 'activity_id'] as $field) {
            $selection = $command->selection()->toArray(); $selection[$field] = $field === 'activity_id' ? ObservationId::generate()->toString() : 'concealed';
            $this->rejects(static fn () => $remove->bus->execute(new RemoveCapturedDetail(DetailSelection::fromArray($selection), $detail->id(), DetailKind::MESSAGE)));
        }
        $this->rejects(static fn () => $remove->bus->execute(new RemoveCapturedDetail($command->selection(), $detail->id(), DetailKind::TOOL_RESULT)));
        $this->rejects(static fn () => $remove->bus->execute(new RemoveCapturedDetail($command->selection(), ObservationId::generate(), DetailKind::MESSAGE)));
        $this->rejects(static fn () => $remove->bus->execute(new RemoveCapturedDetail($command->selection(), ObservationFixture::phase()->id(), DetailKind::MESSAGE)));
        $remove->bus->execute($command);
        self::assertSame(12, $remove->admissions);
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM fight_execution_contents'));
    }

    #[DataProvider('databases')]
    public function test_that_failure_after_real_delete_rolls_back_and_outer_transactions_preserve_caller_work(string $backend): void
    {
        $db = $this->database($backend); $store = new DbalRecordingTransaction($db); $record = $this->seed($store);
        $detail = ObservationFixture::detail(); $record->record($detail, ProtectedContent::create($detail->detail(), 'PRIVATE'));
        $command = RemovalFixture::command($detail); $before = $this->allState($db);
        $failing = new RemovalFixture($store, static function () use ($db): void {
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM fight_execution_contents'));
            throw new RuntimeException('Injected failure after deletion.');
        });
        try { $failing->bus->execute($command); self::fail('Expected rollback.'); }
        catch (RuntimeException $failure) { self::assertSame('Injected failure after deletion.', $failure->getMessage()); }
        self::assertSame($before, $this->allState($this->connect($db->getParams())));
        self::assertFalse($store->isClosed());
        $remove = new RemovalFixture($store);
        $db->beginTransaction();
        $db->insert('fight_execution_sessions', SessionReference::fromStrings('caller', 'unrelated', 'owned')->toArray());
        try { $remove->bus->execute($command); self::fail('Expected outer transaction rejection.'); }
        catch (LogicException) { self::assertTrue($db->isTransactionActive()); self::assertSame(0, $remove->admissions); }
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM fight_execution_contents'));
        self::assertSame(2, (int) $db->fetchOne('SELECT COUNT(*) FROM fight_execution_sessions'));
        $db->commit();
        self::assertSame(2, (int) $this->connect($db->getParams())->fetchOne('SELECT COUNT(*) FROM fight_execution_sessions'));
        $remove->bus->execute($command);
        self::assertSame(0, (int) $this->connect($db->getParams())->fetchOne('SELECT COUNT(*) FROM fight_execution_contents'));
    }

    #[DataProvider('databases')]
    public function test_that_lost_commit_acknowledgment_is_uncertain_and_fresh_removal_safely_rechecks_admission(string $backend): void
    {
        $db = $this->database($backend); $record = $this->seed(new DbalRecordingTransaction($db));
        $detail = ObservationFixture::detail(); $record->record($detail, ProtectedContent::create($detail->detail(), 'PRIVATE'));
        $proxy = $this->mock(Connection::class);
        foreach (['getDatabasePlatform', 'isTransactionActive', 'beginTransaction', 'executeStatement', 'fetchOne', 'fetchAssociative', 'delete'] as $method) {
            $proxy->shouldReceive($method)->andReturnUsing(static fn (...$arguments) => $db->$method(...$arguments));
        }
        $proxy->shouldReceive('commit')->once()->andReturnUsing(static function () use ($db): void { $db->commit(); throw new RuntimeException('Lost acknowledgment.'); });
        $proxy->shouldNotReceive('rollBack');
        $store = new DbalRecordingTransaction($proxy); $remove = new RemovalFixture($store);
        try { $remove->bus->execute(RemovalFixture::command($detail)); self::fail('Must report uncertain outcome.'); }
        catch (ObservationStorageFailed $failure) { self::assertTrue($failure->outcomeUncertain()); }
        self::assertTrue($store->isClosed()); self::assertSame(1, $remove->admissions);
        $fresh = $this->connect($db->getParams());
        self::assertSame(0, (int) $fresh->fetchOne('SELECT COUNT(*) FROM fight_execution_contents'));
        $repeat = new RemovalFixture(new DbalRecordingTransaction($fresh)); $repeat->bus->execute(RemovalFixture::command($detail));
        self::assertSame(1, $repeat->admissions);
    }

    #[DataProvider('databases')]
    public function test_that_removal_has_no_page_or_chunk_lease_and_uncommitted_deletion_does_not_leak(string $backend): void
    {
        $db = $this->database($backend); $record = $this->seed(new DbalRecordingTransaction($db));
        $detail = ObservationFixture::detail(); $record->record($detail, ProtectedContent::create($detail->detail(), 'abcdef'));
        $second = ObservationFixture::detail(['observation_id' => ObservationId::generate()->toString()]); $record->record($second, ProtectedContent::create($second->detail(), 'other'));
        [$queries] = $this->queries($db); $selection = RemovalFixture::command($detail)->selection();
        self::assertSame(2, $queries->fetch(new ListCapturedMessages($selection, 1, 1))->toArray()['total']);
        self::assertSame(3, $queries->fetch(new GetCapturedDetail($selection, $detail->id(), DetailKind::MESSAGE, 0, 3))->toArray()['chunk']['next_offset']);
        $dir = $this->directory(); $config = $dir.'/config.json';
        file_put_contents($config, json_encode(['params' => $db->getParams(), 'observation' => $detail->toArray(), 'operations' => ['first' => 'remove'], 'pause_after_delete' => true], JSON_THROW_ON_ERROR));
        $worker = $this->worker($config, 'first');
        try {
            $worker->start(); $this->waitFor($dir.'/first-deleted', $worker);
            $during = $queries->fetch(new GetCapturedDetail($selection, $detail->id(), DetailKind::MESSAGE, 3, 3))->toArray();
            self::assertSame('def', base64_decode($during['chunk']['data'], true), 'Independent read wins before commit and sees the old committed body.');
            file_put_contents($dir.'/release', 'commit'); self::assertSame(0, $worker->wait(), $worker->getErrorOutput());
            self::assertSame('removed', json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status']);
            $after = $queries->fetch(new GetCapturedDetail($selection, $detail->id(), DetailKind::MESSAGE, 3, 3))->toArray();
            self::assertNull($after['chunk']); self::assertSame('missing', $after['reference']['availability']);
            $pages = [];
            foreach ([1, 2] as $page) { $pages = [...$pages, ...$queries->fetch(new ListCapturedMessages($selection, $page, 1))->toArray()['messages']]; }
            $removed = array_values(array_filter($pages, static fn ($row) => $row['reference']['observation_id'] === $detail->id()->toString()))[0];
            self::assertNull($removed['chunk']); self::assertSame('missing', $removed['reference']['availability']);
        } finally { $worker->stop(); }
    }

    #[DataProvider('races')]
    public function test_that_competing_public_removals_and_recording_retries_obey_retained_comparison_boundaries(string $backend, string $competitor, bool $removalFirst, bool $retained): void
    {
        $db = $this->database($backend); $store = new DbalRecordingTransaction($db); $record = $this->seed($store);
        $detail = ObservationFixture::detail(); $content = ProtectedContent::create($detail->detail(), 'private-body'); $record->record($detail, $content);
        if (!$retained) {
            // Consumer-owned identity purge fixture, deliberately not a public metadata-purge capability.
            (new RemovalFixture($store))->bus->execute(RemovalFixture::command($detail));
            $store->commitTransactional(static fn () => $db->delete('fight_execution_observations', ['producer_id' => $detail->producerId(), 'observation_id' => $detail->id()->toString()]));
        }
        $operations = $removalFirst ? ['first' => 'remove', 'second' => $competitor] : ['first' => $competitor, 'second' => 'remove'];
        $dir = $this->directory(); $config = $dir.'/config.json';
        file_put_contents($config, json_encode(['params' => $db->getParams(), 'observation' => $detail->toArray(), 'content' => $content->toArray(), 'operations' => $operations], JSON_THROW_ON_ERROR));
        $first = $this->worker($config, 'first'); $second = $this->worker($config, 'second');
        try {
            $first->start(); $this->waitFor($dir.'/first-admitted', $first);
            $second->start(); $this->waitFor($dir.'/second-started', $second);
            self::assertFileDoesNotExist($dir.'/second-admitted', 'First worker already owns the common serialization lock.');
            file_put_contents($dir.'/release', 'proceed');
            self::assertSame(0, $first->wait(), $first->getErrorOutput()); self::assertSame(0, $second->wait(), $second->getErrorOutput());
            $a = json_decode($first->getOutput(), true, flags: JSON_THROW_ON_ERROR); $b = json_decode($second->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $removal = $removalFirst ? $a : $b; $retry = $removalFirst ? $b : $a;
            self::assertSame(!$retained && $removalFirst ? DetailRemovalRejected::class : 'removed', $removal['status']);
            $expected = 'recorded';
            if ($retained && $competitor === 'remove') { $expected = 'removed'; }
            elseif ($retained && $competitor === 'metadata-conflict') { $expected = ObservationConflict::class; }
            elseif ($retained && $removalFirst) { $expected = ProtectedContentRemoved::class; }
            elseif ($retained && $competitor === 'body-conflict') { $expected = ObservationConflict::class; }
            self::assertSame($expected, $retry['status']);
            self::assertSame(1, $a['admissions']); self::assertSame(1, $b['admissions']);
            self::assertSame(0, $removal['events']); self::assertSame($retained ? 0 : 1, $retry['events']);
            $body = $db->fetchOne('SELECT payload FROM fight_execution_contents');
            if (!$retained && $removalFirst) {
                self::assertIsString($body);
                self::assertSame($competitor === 'body-conflict' ? 'changed-private-body' : 'private-body', json_decode($body, true, flags: JSON_THROW_ON_ERROR)['body']);
            } else { self::assertFalse($body); }
            self::assertSame(4, (int) $db->fetchOne('SELECT COUNT(*) FROM fight_execution_observations'));
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM fight_execution_sessions'));
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM fight_execution_phases'));
        } finally { $first->stop(); $second->stop(); }
    }

    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; }
    }

    public static function races(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            foreach (['remove', 'retry', 'body-conflict', 'metadata-conflict'] as $competitor) {
                foreach ([true, false] as $first) {
                    yield $backend.'-'.$competitor.'-'.($first ? 'remove-first' : 'retry-first').'-retained' => [$backend, $competitor, $first, true];
                    if (in_array($competitor, ['retry', 'body-conflict'], true)) {
                        yield $backend.'-'.$competitor.'-'.($first ? 'remove-first' : 'retry-first').'-purged' => [$backend, $competitor, $first, false];
                    }
                }
            }
        }
    }

    private function seed(DbalRecordingTransaction $store): RecordingFixture
    {
        $fixture = new RecordingFixture($store);
        foreach ([ObservationFixture::session(), ObservationFixture::phase(), ObservationFixture::activity()] as $observation) { $fixture->record($observation); }
        return $fixture;
    }

    private function queries(Connection $db): array
    {
        $detailAdmission = new class implements DetailReadAdmission {
            public DetailAccess $access;
            public function __construct() { $this->access = DetailAccess::create(['project' => null], metadataGrants: ['project' => null], bodyGrants: ['project' => null]); }
            public function currentAccess(DetailSelection $selection): DetailAccess { return $this->access; }
        };
        $safeAdmission = new class implements ExecutionReadAdmission {
            public function currentAccess(ExecutionSelection $selection): ExecutionAccess { return ExecutionAccess::create(['project' => null], detailGrants: ['project' => null]); }
        };
        $router = new InMemoryQueryRouter();
        $router->registerHandler(GetCapturedDetailHandler::queryRegistration(), new GetCapturedDetailHandler($detailAdmission, new DbalDetailReader($db)));
        $router->registerHandler(ListCapturedMessagesHandler::queryRegistration(), new ListCapturedMessagesHandler($detailAdmission, new DbalDetailReader($db)));
        $router->registerHandler(ListPhaseActivityHandler::queryRegistration(), new ListPhaseActivityHandler($safeAdmission, new DbalActivityReader($db)));
        $router->registerHandler(GetExecutionHandler::queryRegistration(), new GetExecutionHandler($safeAdmission, new DbalExecutionReader($db)));
        $router->registerHandler(SummarizeExecutionsHandler::queryRegistration(), new SummarizeExecutionsHandler($safeAdmission, new DbalExecutionReader($db)));
        $router->registerHandler(ListExecutionPhasesHandler::queryRegistration(), new ListExecutionPhasesHandler($safeAdmission, new DbalPhaseReader($db)));
        return [new RoutingQueryBus($router), $detailAdmission];
    }

    private function summary(RoutingQueryBus $bus): array
    {
        return [$bus->fetch(new GetExecution('project', 'session'))->toArray(), $bus->fetch(new SummarizeExecutions(ExecutionSelection::create(['project'])))->toArray(), $bus->fetch(new ListExecutionPhases('project', 'session'))->toArray()];
    }

    private function rejects(callable $operation): void
    {
        try { $operation(); self::fail('Expected concealed removal rejection.'); }
        catch (DetailRemovalRejected $failure) { self::assertSame('Captured detail removal was not admitted.', $failure->getMessage()); }
    }

    private function safeState(Connection $db): array
    {
        return array_map(static fn ($table) => $db->fetchAllAssociative('SELECT * FROM '.$table), array_diff(DbalObservationSchema::TABLES, ['fight_execution_contents']));
    }

    private function allState(Connection $db): array
    {
        return array_map(static fn ($table) => $db->fetchAllAssociative('SELECT * FROM '.$table), DbalObservationSchema::TABLES);
    }

    private function database(string $backend): Connection
    {
        if ($backend === 'sqlite') { $params = ['driver' => 'pdo_sqlite', 'path' => $this->directory().'/observations.sqlite']; }
        else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn, 'Actual disposable '.$backend.' is required; skips do not qualify removal.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }
        $db = $this->connect($params);
        foreach (array_reverse(DbalObservationSchema::TABLES) as $table) { $db->executeStatement('DROP TABLE IF EXISTS '.$table); }
        (new DbalObservationSchema())->install($db);
        return $db;
    }

    private function connect(array $params): Connection
    {
        $db = DriverManager::getConnection($params);
        if ($params['driver'] === 'pdo_sqlite') { $db->executeStatement('PRAGMA foreign_keys = ON'); $db->executeStatement('PRAGMA busy_timeout = 10000'); }
        $this->connections[] = $db;
        return $db;
    }

    private function directory(): string
    {
        $path = sys_get_temp_dir().'/fight-removal-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($path, 0700)); $this->directories[] = $path;
        return $path;
    }

    private function worker(string $config, string $role): Process
    {
        return new Process([PHP_BINARY, __DIR__.'/../../../TestCase/Execution/remove-worker.php', $config, $role], timeout: 20);
    }

    private function waitFor(string $path, Process $worker): void
    {
        $deadline = microtime(true) + 10;
        while (!file_exists($path)) {
            if (!$worker->isRunning() || microtime(true) > $deadline) { self::fail('Worker barrier failed: '.$worker->getErrorOutput().$worker->getOutput()); }
            usleep(10000);
        }
        self::assertFileExists($path);
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) { $connection->close(); }
        foreach ($this->directories as $directory) { $this->removeTemporaryDirectory($directory, 'fight-removal-'); }
        parent::tearDown();
    }
}
