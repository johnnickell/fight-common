<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Execution\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Execution\Dbal\DbalObservationSchema;
use Fight\Common\Adapter\Execution\Dbal\DbalObservationStore;
use Fight\Common\Application\Execution\CommittedObservationDispatchFailed;
use Fight\Common\Application\Execution\InvalidObservationRelationship;
use Fight\Common\Application\Execution\ObservationConflict;
use Fight\Common\Application\Execution\ObservationStorageFailed;
use Fight\Common\Application\Execution\RecordingDenied;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Domain\Execution\Observation;
use Fight\Common\Domain\Execution\ObservationId;
use Fight\Common\Domain\Execution\ObservationOutcome;
use Fight\Common\Domain\Execution\ObservationRecorded;
use Fight\Common\Domain\Execution\PhaseReference;
use Fight\Common\Domain\Execution\SessionReference;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\Execution\ObservationFixture;
use Fight\Test\Common\TestCase\Execution\RecordingFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class DbalObservationStoreIntegrationTest extends UnitTestCase
{
    private array $directories = [];
    private array $connections = [];

    #[DataProvider('databases')]
    public function test_that_commands_roundtrip_complete_session_parent_and_child_evidence(string $backend): void
    {
        $connection = $this->database($backend);
        $store = new DbalObservationStore($connection);
        $fixture = new RecordingFixture($store);
        $session = ObservationFixture::session([
            'started_at' => '2026-10-20T12:00:00.000018Z',
            'ended_at' => '2026-10-20T12:34:56.123456Z',
            'outcome' => 'succeeded',
            'extensions' => ['consumer/version' => 'v1', 'consumer/label' => '測定'],
        ]);
        self::assertSame(0, $this->rowCount($connection, 'observations'));
        $fixture->record($session);
        $parent = ObservationFixture::phase(['phase' => PhaseReference::fromStrings('session', 'parent', null, 'reviewer', 'review', 2)->toArray()]);
        $fixture->record($parent);
        $child = ObservationFixture::phase([
            'observation_id' => '019a1234-5678-4abc-8def-ffffffffffff',
            'phase' => PhaseReference::fromStrings('session', 'child', 'parent', 'builder', 'repair', 3)->toArray(),
            'started_at' => '2026-10-20T12:00:00.000019Z',
            'outcome' => 'unknown',
        ]);
        $fixture->record($child);
        $reopened = new DbalObservationStore($this->connect($connection->getParams()));
        foreach ([$session, $parent, $child] as $observation) {
            $saved = $reopened->get('instrumentation', $observation->id);
            self::assertSame($observation->toArray(), $saved->observation->toArray());
            self::assertSame('123456', $saved->observation->occurredAt->format('u'));
            self::assertSame($observation->id->toString(), $saved->observation->id->toString());
            self::assertSame(27, strlen($saved->recordedAt->format('Y-m-d\TH:i:s.u\Z')));
        }
        self::assertSame('000018', $reopened->get('instrumentation', $session->id)->observation->startedAt->format('u'));
        self::assertSame(ObservationOutcome::UNKNOWN, $reopened->get('instrumentation', $child->id)->observation->outcome);
        self::assertNull($reopened->get('instrumentation', $child->id)->observation->endedAt);
        self::assertSame($child->phase->toArray(), $reopened->phase('project', 'child')->toArray());
        self::assertSame(3, $this->rowCount($connection, 'observations'));
        self::assertSame(2, $this->rowCount($connection, 'phases'));
        self::assertSame(1, $this->rowCount($connection, 'sessions'));
        self::assertFalse($connection->isTransactionActive());
    }

    #[DataProvider('databases')]
    public function test_that_retries_preserve_recording_time_and_recheck_current_trust(string $backend): void
    {
        $connection = $this->database($backend);
        $store = new DbalObservationStore($connection);
        $fixture = new RecordingFixture($store);
        $observation = ObservationFixture::session();
        $events = 0;
        $fixture->events->addHandler(ClassName::underscore(ObservationRecorded::class), static function () use (&$events): void { ++$events; });
        $fixture->record($observation);
        $recordedAt = $store->get('instrumentation', $observation->id)->recordedAt;
        usleep(1000);
        $fixture->record(Observation::fromArray($observation->toArray()));
        self::assertEquals($recordedAt, $store->get('instrumentation', $observation->id)->recordedAt);
        self::assertSame(1, $events);
        self::assertSame(2, $fixture->admissions);
        try {
            $fixture->record(ObservationFixture::session(['outcome' => 'failed']));
            self::fail('Expected same-ID content conflict.');
        } catch (ObservationConflict) {
            self::assertSame(ObservationOutcome::UNKNOWN, $store->get('instrumentation', $observation->id)->observation->outcome);
        }
        $fixture->allowed = false;
        try {
            $fixture->record($observation);
            self::fail('Expected revoked retry rejection.');
        } catch (RecordingDenied $exception) {
            self::assertSame('Observation recording is not permitted.', $exception->getMessage());
        }
        self::assertSame(4, $fixture->admissions);
        self::assertSame(1, $this->rowCount($connection, 'observations'));
        self::assertSame(1, $events);
        // Same UUID belongs to a different producer's independent retry namespace.
        $other = ObservationFixture::session(['producer_id' => 'other-producer']);
        $store->commitTransactional(static fn () => $store->append($other));
        self::assertSame('other-producer', $store->get('other-producer', $observation->id)->observation->producerId);
        $caseDistinct = ObservationFixture::session(['producer_id' => 'Other-producer']);
        $store->commitTransactional(static fn () => $store->append($caseDistinct));
        self::assertSame('Other-producer', $store->get('Other-producer', $observation->id)->observation->producerId);
        self::assertSame(3, $this->rowCount($connection, 'observations'));
    }

    #[DataProvider('databases')]
    public function test_that_current_session_phase_and_parent_scope_is_not_granted_by_existing_records(string $backend): void
    {
        $connection = $this->database($backend);
        $store = new DbalObservationStore($connection);
        $fixture = new RecordingFixture($store);
        $session = ObservationFixture::session();
        $phase = ObservationFixture::phase();
        $fixture->record($session);
        $fixture->record($phase);
        $child = ObservationFixture::phase([
            'observation_id' => ObservationId::generate()->toString(),
            'phase' => PhaseReference::fromStrings('session', 'child', 'phase', 'agent', 'work', 1)->toArray(),
        ]);
        $fixture->permittedSessions = [];
        try {
            $fixture->record($session);
            self::fail('Existing session does not bypass current scope.');
        } catch (RecordingDenied) {
            self::assertSame(2, $this->rowCount($connection, 'observations'));
        }
        $fixture->permittedSessions = ['session'];
        $fixture->permittedPhases = ['child'];
        foreach ([$phase, $child] as $observation) {
            try {
                $fixture->record($observation);
                self::fail('Existing target or parent does not grant phase scope.');
            } catch (RecordingDenied $exception) {
                self::assertSame('Observation recording is not permitted.', $exception->getMessage());
            }
        }
        self::assertSame(2, $this->rowCount($connection, 'observations'));
        self::assertSame(1, $this->rowCount($connection, 'phases'));
        $fixture->permittedPhases = ['phase', 'child'];
        $fixture->record($child);
        self::assertSame(3, $this->rowCount($connection, 'observations'));
    }

    #[DataProvider('databases')]
    public function test_that_cross_scope_and_inconsistent_relationships_reject_without_partial_writes(string $backend): void
    {
        $connection = $this->database($backend);
        $store = new DbalObservationStore($connection);
        $fixture = new RecordingFixture($store);
        $fixture->record(ObservationFixture::session());
        $fixture->record(ObservationFixture::phase());
        $otherSession = SessionReference::fromStrings('project', 'other-session', 'build:other');
        $fixture->record(ObservationFixture::session(['observation_id' => ObservationId::generate()->toString(), 'session' => $otherSession->toArray()]));
        $otherProject = SessionReference::fromStrings('concealed', 'session', 'build:concealed');
        $store->commitTransactional(static fn () => $store->addSession($otherProject));
        $invalid = [
            ObservationFixture::session(['session' => SessionReference::fromStrings('project', 'session', 'wrong-reference')->toArray()]),
            ObservationFixture::phase(['session' => SessionReference::fromStrings('project', 'missing', 'build:missing')->toArray(), 'phase' => PhaseReference::fromStrings('missing', 'new', null, 'agent', 'work', 1)->toArray()]),
            ObservationFixture::phase(['phase' => PhaseReference::fromStrings('session', 'new', 'missing', 'agent', 'work', 1)->toArray()]),
            ObservationFixture::phase(['session' => $otherSession->toArray(), 'phase' => PhaseReference::fromStrings('other-session', 'child', 'phase', 'agent', 'work', 1)->toArray()]),
            ObservationFixture::phase(['phase' => PhaseReference::fromStrings('session', 'phase', null, 'different-agent', 'review', 1)->toArray()]),
        ];
        foreach ($invalid as $observation) {
            try {
                $fixture->record($observation);
                self::fail('Expected unavailable/inconsistent relationship.');
            } catch (InvalidObservationRelationship $exception) {
                self::assertSame('Observation relationship is unavailable or inconsistent.', $exception->getMessage());
            }
        }
        foreach ([
            ObservationFixture::session(['producer_id' => 'untrusted']),
            ObservationFixture::session(['session' => $otherProject->toArray()]),
            ObservationFixture::session(['session' => SessionReference::fromStrings('unknown-project', 'session', 'build:concealed')->toArray()]),
        ] as $observation) {
            try {
                $fixture->record($observation);
                self::fail('Expected current trust rejection.');
            } catch (RecordingDenied $exception) {
                self::assertSame('Observation recording is not permitted.', $exception->getMessage());
            }
        }
        self::assertSame(3, $this->rowCount($connection, 'observations'));
        self::assertSame(3, $this->rowCount($connection, 'sessions'));
        self::assertSame(1, $this->rowCount($connection, 'phases'));
        // Database FKs independently reject cross-session parents, not only Application checks.
        try {
            $connection->insert('fight_execution_phases', ['project_id' => 'project'] + PhaseReference::fromStrings('other-session', 'illegal', 'phase', 'agent', 'work', 1)->toArray());
            self::fail('Expected referential integrity rejection.');
        } catch (Exception) {
            self::assertSame(1, $this->rowCount($connection, 'phases'));
        }
    }

    #[DataProvider('databases')]
    public function test_that_database_trigger_failure_rolls_back_relationships_and_emits_nothing(string $backend): void
    {
        $connection = $this->database($backend);
        $store = new DbalObservationStore($connection);
        $fixture = new RecordingFixture($store);
        $events = 0;
        $fixture->events->addHandler(ClassName::underscore(ObservationRecorded::class), static function () use (&$events): void { ++$events; });
        $this->failureTrigger($connection, $backend);
        try {
            $fixture->record(ObservationFixture::session());
            self::fail('Expected late insert failure.');
        } catch (ObservationStorageFailed $exception) {
            self::assertFalse($exception->outcomeUncertain);
            self::assertSame('Observation storage was rolled back.', $exception->getMessage());
        }
        self::assertSame(0, $this->rowCount($connection, 'sessions'));
        self::assertSame(0, $this->rowCount($connection, 'observations'));
        self::assertSame(0, $events);
        self::assertFalse($store->isClosed());
        $this->dropFailureTrigger($connection, $backend);
        $fixture->record(ObservationFixture::session());
        $events = 0;
        $this->failureTrigger($connection, $backend, true);
        try {
            $fixture->record(ObservationFixture::phase());
            self::fail('Expected phase evidence failure.');
        } catch (ObservationStorageFailed $exception) {
            self::assertFalse($exception->outcomeUncertain);
        }
        self::assertSame(0, $this->rowCount($connection, 'phases'));
        self::assertSame(1, $this->rowCount($connection, 'sessions'));
        self::assertSame(1, $this->rowCount($connection, 'observations'));
        self::assertSame(0, $events);
        self::assertFalse($connection->isTransactionActive());
        $this->dropFailureTrigger($connection, $backend);
    }

    #[DataProvider('databases')]
    public function test_that_subscriber_failure_occurs_after_real_commit_and_retry_does_not_replay(string $backend): void
    {
        $connection = $this->database($backend);
        $otherConnection = $this->connect($connection->getParams());
        $store = new DbalObservationStore($connection);
        $fixture = new RecordingFixture($store);
        $observation = ObservationFixture::session();
        $subscribers = [];
        $fixture->events->addHandler(ClassName::underscore(ObservationRecorded::class), static function (EventMessage $message) use (&$subscribers, $otherConnection, $connection): void {
            self::assertFalse($connection->isTransactionActive());
            self::assertSame(1, (int) $otherConnection->fetchOne('SELECT COUNT(*) FROM fight_execution_observations'));
            self::assertSame(1, (int) $otherConnection->fetchOne('SELECT COUNT(*) FROM fight_execution_sessions'));
            self::assertSame(['observation_id', 'producer_id', 'project_id', 'session_id', 'phase_id', 'recorded_at'], array_keys($message->payload()->toArray()));
            $subscribers[] = 'failed';
            throw new RuntimeException('private subscriber detail');
        });
        $fixture->events->addHandler(ClassName::underscore(ObservationRecorded::class), static function () use (&$subscribers): void { $subscribers[] = 'completed'; });
        try {
            $fixture->record($observation);
            self::fail('Expected explicitly committed event failure.');
        } catch (CommittedObservationDispatchFailed $exception) {
            self::assertInstanceOf(EventDispatchFailed::class, $exception->getPrevious());
            self::assertSame('Observation committed but event dispatch failed.', $exception->getMessage());
            self::assertTrue($observation->id->equals($exception->event->id));
        }
        self::assertSame(['failed', 'completed'], $subscribers);
        self::assertNotNull($store->get('instrumentation', $observation->id));
        $fixture->record($observation);
        self::assertSame(['failed', 'completed'], $subscribers);
        self::assertSame(2, $fixture->admissions);
    }

    #[DataProvider('databases')]
    public function test_that_caller_work_is_not_committed_or_rolled_back_by_recording(string $backend): void
    {
        $connection = $this->database($backend);
        $store = new DbalObservationStore($connection);
        $fixture = new RecordingFixture($store);
        $connection->beginTransaction();
        $connection->insert('fight_execution_sessions', SessionReference::fromStrings('caller', 'unrelated', 'owned')->toArray());
        try {
            $fixture->record(ObservationFixture::session());
            self::fail('Expected outer transaction rejection.');
        } catch (LogicException) {
            self::assertTrue($connection->isTransactionActive());
            self::assertSame(1, $this->rowCount($connection, 'sessions'));
            self::assertSame(0, $this->rowCount($connection, 'observations'));
            self::assertSame(0, $fixture->admissions);
        }
        $connection->commit();
        self::assertSame(1, $this->rowCount($this->connect($connection->getParams()), 'sessions'));
    }

    #[DataProvider('databases')]
    public function test_that_lost_commit_acknowledgment_reports_uncertainty_despite_committed_data(string $backend): void
    {
        $connection = $this->database($backend);
        // The proxy loses only the acknowledgment after the real database commits.
        $proxy = $this->mock(Connection::class);
        foreach (['getDatabasePlatform', 'isTransactionActive', 'beginTransaction', 'executeStatement', 'fetchOne', 'fetchAssociative', 'insert'] as $method) {
            $proxy->shouldReceive($method)->andReturnUsing(static fn (...$arguments) => $connection->$method(...$arguments));
        }
        $proxy->shouldReceive('commit')->once()->andReturnUsing(static function () use ($connection): void {
            $connection->commit();
            throw new RuntimeException('Injected loss of commit acknowledgment.');
        });
        $proxy->shouldNotReceive('rollBack');
        $store = new DbalObservationStore($proxy);
        $fixture = new RecordingFixture($store);
        $events = 0;
        $fixture->events->addHandler(ClassName::underscore(ObservationRecorded::class), static function () use (&$events): void { ++$events; });
        $observation = ObservationFixture::session();
        try {
            $fixture->record($observation);
            self::fail('Expected uncertain outcome, not a false rollback claim.');
        } catch (ObservationStorageFailed $exception) {
            self::assertTrue($exception->outcomeUncertain);
        }
        self::assertTrue($store->isClosed());
        self::assertSame(0, $events);
        $reopened = new DbalObservationStore($this->connect($connection->getParams()));
        self::assertSame($observation->toArray(), $reopened->get('instrumentation', $observation->id)->observation->toArray());
        $freshFixture = new RecordingFixture($reopened);
        $freshFixture->events->addHandler(ClassName::underscore(ObservationRecorded::class), static function (): void { self::fail('Matching uncertain retry must not replay an event.'); });
        $freshFixture->record($observation);
        self::assertSame(1, $freshFixture->admissions);
        self::assertSame(1, $this->rowCount($connection, 'observations'));
    }

    #[DataProvider('races')]
    public function test_that_independent_competing_retries_serialize_and_compare_content(string $backend, bool $phase, bool $conflict): void
    {
        $connection = $this->database($backend);
        $fixture = new RecordingFixture(new DbalObservationStore($connection));
        if ($phase) { $fixture->record(ObservationFixture::session()); }
        $observation = $phase ? ObservationFixture::phase() : ObservationFixture::session();
        $directory = $this->directory();
        $config = $directory.'/config.json';
        file_put_contents($config, json_encode(['params' => $connection->getParams(), 'observation' => $observation->toArray(), 'conflict' => $conflict], JSON_THROW_ON_ERROR));
        $first = new Process([PHP_BINARY, __DIR__.'/../../../TestCase/Execution/record-worker.php', $config, 'first'], timeout: 20);
        $second = new Process([PHP_BINARY, __DIR__.'/../../../TestCase/Execution/record-worker.php', $config, 'second'], timeout: 20);
        try {
            $first->start();
            $this->waitFor($directory.'/first-admitted', $first);
            $second->start();
            $this->waitFor($directory.'/second-started', $second);
            usleep(200000);
            self::assertTrue($second->isRunning(), $second->getErrorOutput().$second->getOutput());
            self::assertFileDoesNotExist($directory.'/second-admitted');
            file_put_contents($directory.'/release', 'release');
            self::assertSame(0, $first->wait(), $first->getErrorOutput());
            self::assertSame(0, $second->wait(), $second->getErrorOutput());
            $winner = json_decode($first->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $retry = json_decode($second->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('recorded', $winner['status']);
            self::assertSame($conflict ? ObservationConflict::class : 'recorded', $retry['status']);
            self::assertSame(1, $winner['events']);
            self::assertSame(0, $retry['events']);
            self::assertSame(1, $winner['admissions']);
            self::assertSame(1, $retry['admissions']);
            self::assertSame($winner['recorded_at'], $retry['recorded_at']);
            self::assertSame('unknown', $retry['outcome']);
            self::assertSame($observation->id->toString(), $retry['id']);
            self::assertSame($phase ? 2 : 1, $this->rowCount($connection, 'observations'));
            self::assertSame(1, $this->rowCount($connection, 'sessions'));
            self::assertSame($phase ? 1 : 0, $this->rowCount($connection, 'phases'));
        } finally {
            file_put_contents($directory.'/release', 'release');
            $first->stop();
            $second->stop();
        }
    }

    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; }
    }

    public static function races(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            foreach ([false, true] as $phase) {
                foreach ([false, true] as $conflict) {
                    yield $backend.'-'.($phase ? 'phase' : 'session').'-'.($conflict ? 'conflict' : 'matching') => [$backend, $phase, $conflict];
                }
            }
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) { $connection->close(); }
        foreach ($this->directories as $directory) { $this->removeTemporaryDirectory($directory, 'fight-execution-'); }
        parent::tearDown();
    }

    private function database(string $backend): Connection
    {
        if ($backend === 'sqlite') {
            $params = ['driver' => 'pdo_sqlite', 'path' => $this->directory().'/observations.sqlite'];
        } else {
            $dsn = getenv($backend === 'mysql' ? 'FIGHT_COMMON_MYSQL_DSN' : 'FIGHT_COMMON_POSTGRESQL_DSN');
            self::assertIsString($dsn, 'Disposable '.$backend.' DSN is required; skipped server cases do not qualify the adapter.');
            $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($dsn);
        }
        $connection = $this->connect($params);
        foreach (array_reverse(DbalObservationSchema::TABLES) as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }
        if ($backend === 'postgresql') { $connection->executeStatement('DROP FUNCTION IF EXISTS fight_execution_fail()'); }
        (new DbalObservationSchema())->install($connection);
        return $connection;
    }

    private function connect(array $params): Connection
    {
        $connection = DriverManager::getConnection($params);
        if ($params['driver'] === 'pdo_sqlite') {
            $connection->executeStatement('PRAGMA foreign_keys = ON');
            $connection->executeStatement('PRAGMA busy_timeout = 10000');
        }
        $this->connections[] = $connection;
        return $connection;
    }

    private function directory(): string
    {
        $path = sys_get_temp_dir().'/fight-execution-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($path, 0700));
        $this->directories[] = $path;
        return $path;
    }

    private function rowCount(Connection $connection, string $suffix): int
    {
        return (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_execution_'.$suffix);
    }

    private function failureTrigger(Connection $connection, string $backend, bool $phase = false): void
    {
        if ($backend === 'sqlite') {
            $connection->executeStatement("CREATE TRIGGER fight_execution_fail BEFORE INSERT ON fight_execution_observations BEGIN SELECT RAISE(ABORT, 'injected'); END");
        } elseif ($backend === 'mysql') {
            $id = $phase ? ObservationFixture::phase()->id : ObservationFixture::session()->id;
            $connection->executeStatement("ALTER TABLE fight_execution_observations ADD CONSTRAINT fight_execution_fail CHECK (observation_id <> '".$id->toString()."')");
        } else {
            $connection->executeStatement("CREATE FUNCTION fight_execution_fail() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'injected'; END; $$");
            $connection->executeStatement('CREATE TRIGGER fight_execution_fail BEFORE INSERT ON fight_execution_observations FOR EACH ROW EXECUTE FUNCTION fight_execution_fail()');
        }
    }

    private function dropFailureTrigger(Connection $connection, string $backend): void
    {
        if ($backend === 'mysql') {
            $connection->executeStatement('ALTER TABLE fight_execution_observations DROP CHECK fight_execution_fail');
            return;
        }
        $sql = 'DROP TRIGGER fight_execution_fail';
        if ($backend === 'postgresql') { $sql .= ' ON fight_execution_observations'; }
        $connection->executeStatement($sql);
        if ($backend === 'postgresql') { $connection->executeStatement('DROP FUNCTION fight_execution_fail()'); }
    }

    private function waitFor(string $path, Process $process): void
    {
        $deadline = microtime(true) + 10;
        while (!file_exists($path)) {
            if (!$process->isRunning() || microtime(true) > $deadline) {
                self::fail('Worker did not reach barrier: '.$process->getErrorOutput().$process->getOutput());
            }
            usleep(10000);
        }
        self::assertFileExists($path);
    }
}
