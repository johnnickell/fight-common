<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Execution\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Tools\DsnParser;
use Fight\Common\Adapter\Execution\Dbal\DbalObservationRepository;
use Fight\Common\Adapter\Execution\Dbal\DbalObservationSchema;
use Fight\Common\Adapter\Execution\Dbal\DbalPhaseRepository;
use Fight\Common\Adapter\Execution\Dbal\DbalRecordingTransaction;
use Fight\Common\Adapter\Execution\Dbal\DbalSessionRepository;
use Fight\Common\Application\Execution\Observation\Exception\CommittedObservationDispatchFailed;
use Fight\Common\Application\Execution\Observation\Exception\InvalidObservationRelationship;
use Fight\Common\Application\Execution\Observation\Exception\ObservationConflict;
use Fight\Common\Application\Execution\Observation\Exception\ObservationStorageFailed;
use Fight\Common\Application\Execution\Observation\Exception\RecordingDenied;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Domain\Execution\Observation\Command\RecordPhaseObservation;
use Fight\Common\Domain\Execution\Observation\Command\RecordSessionObservation;
use Fight\Common\Domain\Execution\Observation\Event\ObservationRecorded;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Observation\ObservationId;
use Fight\Common\Domain\Execution\Observation\ObservationOutcome;
use Fight\Common\Domain\Execution\Phase\PhaseReference;
use Fight\Common\Domain\Execution\Session\Session;
use Fight\Common\Domain\Execution\Session\SessionReference;
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
final class DbalRecordingTransactionIntegrationTest extends UnitTestCase
{
    private array $directories = [];
    private array $connections = [];

    #[DataProvider('databases')]
    public function test_that_commands_roundtrip_complete_session_parent_and_child_evidence(string $backend): void
    {
        $connection = $this->database($backend);
        $store = new DbalRecordingTransaction($connection);
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
        $reopened = new DbalRecordingTransaction($this->connect($connection->getParams()));
        foreach ([$session, $parent, $child] as $observation) {
            $saved = (new DbalObservationRepository($reopened))->getById('instrumentation', $observation->id());
            self::assertSame($observation->toArray(), $saved->toArray());
            self::assertSame('123456', $saved->occurredAt()->format('u'));
            self::assertSame($observation->id()->toString(), $saved->id()->toString());
            self::assertSame(27, strlen($saved->recordedAt()->format('Y-m-d\TH:i:s.u\Z')));
        }
        self::assertSame('000018', (new DbalObservationRepository($reopened))->getById('instrumentation', $session->id())->startedAt()->format('u'));
        self::assertSame(ObservationOutcome::UNKNOWN, (new DbalObservationRepository($reopened))->getById('instrumentation', $child->id())->outcome());
        self::assertNull((new DbalObservationRepository($reopened))->getById('instrumentation', $child->id())->endedAt());
        self::assertSame($child->phase()->toArray(), (new DbalPhaseRepository($reopened))->getById('project', 'child')->reference()->toArray());
        self::assertSame(3, $this->rowCount($connection, 'observations'));
        self::assertSame(2, $this->rowCount($connection, 'phases'));
        self::assertSame(1, $this->rowCount($connection, 'sessions'));
        self::assertFalse($connection->isTransactionActive());
    }

    #[DataProvider('databases')]
    public function test_that_current_records_reconstruct_as_independent_roots_and_retries_preserve_evidence(string $backend): void
    {
        $connection = $this->database($backend);
        $writer = new RecordingFixture(new DbalRecordingTransaction($connection));
        $writer->record(ObservationFixture::session());
        $writer->record(ObservationFixture::phase(['phase' => PhaseReference::fromStrings('session', 'parent', null, 'reviewer', 'review', 2)->toArray()]));
        $writer->record(ObservationFixture::phase([
            'observation_id' => '019a1234-5678-4abc-8def-ffffffffffff',
            'phase' => PhaseReference::fromStrings('session', 'child', 'parent', 'builder', 'repair', 3)->toArray(),
        ]));
        $rows = $connection->fetchAllAssociative('SELECT * FROM fight_execution_observations');
        $transaction = new DbalRecordingTransaction($this->connect($connection->getParams()));
        $sessions = new DbalSessionRepository($transaction);
        $phases = new DbalPhaseRepository($transaction);
        $observations = new DbalObservationRepository($transaction);
        self::assertNull($sessions->getById('other-project', 'session'));
        self::assertNull($phases->getById('other-project', 'child'));
        self::assertSame('build:147', $sessions->getById('project', 'session')->consumerReference());
        $child = $phases->getById('project', 'child');
        self::assertSame('project', $child->projectId());
        self::assertSame('child', $child->id());
        self::assertSame('session', $child->sessionId());
        self::assertSame('parent', $child->parentPhaseId());
        self::assertSame('builder', $child->agentId());
        self::assertSame('repair', $child->name());
        self::assertSame(3, $child->attempt());
        self::assertTrue($child->belongsTo($sessions->getById('project', 'session')));
        self::assertTrue($child->acceptsParent($phases->getById('project', 'parent')));
        $fixture = new RecordingFixture($transaction);
        $fixture->events()->addHandler(ClassName::underscore(ObservationRecorded::class), static function (): void { self::fail('Retained evidence must not redispatch after reconstruction.'); });
        foreach ($rows as $row) {
            self::assertSame(1, (int) $row['version']);
            $id = ObservationId::fromString($row['observation_id']);
            self::assertNull($observations->getById('other-producer', $id));
            $root = $observations->getById($row['producer_id'], $id);
            self::assertInstanceOf(Observation::class, $root);
            self::assertSame([], get_object_vars($root));
            self::assertSame($row['payload'], $root->comparison());
            self::assertSame($row['recorded_at'], $root->recordedAt()->format('Y-m-d\\TH:i:s.u\\Z'));
            self::assertSame('123456', $root->occurredAt()->format('u'));
            $fixture->record(Observation::fromArray(json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR)));
            self::assertSame($row['recorded_at'], $observations->getById($row['producer_id'], $id)->recordedAt()->format('Y-m-d\\TH:i:s.u\\Z'));
            self::assertSame($row['payload'], $connection->fetchOne('SELECT payload FROM fight_execution_observations WHERE producer_id = ? AND observation_id = ?', [$row['producer_id'], $row['observation_id']]));
        }
        self::assertSame(3, $fixture->admissions());
        self::assertSame(3, $this->rowCount($connection, 'observations'));
        self::assertSame(1, $this->rowCount($connection, 'sessions'));
        self::assertSame(2, $this->rowCount($connection, 'phases'));
    }

    #[DataProvider('databases')]
    public function test_that_reference_mutations_cannot_corrupt_committed_commands_or_retries(string $backend): void
    {
        $connection = $this->database($backend);
        $store = new DbalRecordingTransaction($connection);
        $fixture = new RecordingFixture($store);
        $reopened = new DbalRecordingTransaction($this->connect($connection->getParams()));
        $events = 0;
        $fixture->events()->addHandler(ClassName::underscore(ObservationRecorded::class), static function () use (&$events): void { ++$events; });

        foreach ([false, true] as $phase) {
            $label = str_repeat('測', 85).'x';
            $count = PHP_INT_MIN;
            $enabled = true;
            $expectedExtensions = ['consumer/count' => PHP_INT_MIN, 'consumer/enabled' => true, 'consumer/label' => str_repeat('測', 85).'x', 'consumer/unknown' => null];
            $extensions = ['consumer/unknown' => null, 'consumer/label' => &$label, 'consumer/enabled' => &$enabled, 'consumer/count' => &$count];
            $observation = $phase ? ObservationFixture::phase(['extensions' => $extensions]) : ObservationFixture::session(['extensions' => $extensions]);
            $expected = $phase ? ObservationFixture::phase(['extensions' => $expectedExtensions]) : ObservationFixture::session(['extensions' => $expectedExtensions]);
            $command = $phase ? new RecordPhaseObservation($observation) : new RecordSessionObservation($observation);
            $comparison = $observation->comparison();

            $label = ['body' => 'not a scalar'];
            $count = str_repeat('x', 257);
            $copy = $command->toArray();
            $copy['extensions']['consumer/enabled'] = ['body' => 'output copy'];
            $fixture->bus()->execute($command);
            $saved = (new DbalObservationRepository($reopened))->getById('instrumentation', $observation->id());
            self::assertSame($expected->toArray(), $saved->toArray());
            self::assertSame($comparison, $observation->comparison());
            self::assertSame($comparison, $saved->comparison());
            self::assertSame($expectedExtensions, $observation->extensions()->toArray());

            $fixture->bus()->execute($command);
            $serialized = json_decode(json_encode($command->toArray(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
            $fixture->bus()->execute($command::fromArray($serialized));
            self::assertEquals($saved->recordedAt(), (new DbalObservationRepository($reopened))->getById('instrumentation', $observation->id())->recordedAt());
            self::assertSame($phase ? 2 : 1, $events);
            self::assertSame($phase ? 6 : 3, $fixture->admissions());
            self::assertSame($phase ? 2 : 1, $this->rowCount($connection, 'observations'));
            self::assertSame(1, $this->rowCount($connection, 'sessions'));
            self::assertSame($phase ? 1 : 0, $this->rowCount($connection, 'phases'));
            self::assertFalse($connection->isTransactionActive());
        }
    }

    #[DataProvider('databases')]
    public function test_that_retries_preserve_recording_time_and_recheck_current_trust(string $backend): void
    {
        $connection = $this->database($backend);
        $store = new DbalRecordingTransaction($connection);
        $fixture = new RecordingFixture($store);
        $observation = ObservationFixture::session();
        $events = 0;
        $fixture->events()->addHandler(ClassName::underscore(ObservationRecorded::class), static function () use (&$events): void { ++$events; });
        $fixture->record($observation);
        $recordedAt = (new DbalObservationRepository($store))->getById('instrumentation', $observation->id())->recordedAt();
        usleep(1000);
        $fixture->record(Observation::fromArray($observation->toArray()));
        self::assertEquals($recordedAt, (new DbalObservationRepository($store))->getById('instrumentation', $observation->id())->recordedAt());
        self::assertSame(1, $events);
        self::assertSame(2, $fixture->admissions());
        try {
            $fixture->record(ObservationFixture::session(['outcome' => 'failed']));
            self::fail('Expected same-ID content conflict.');
        } catch (ObservationConflict) {
            self::assertSame(ObservationOutcome::UNKNOWN, (new DbalObservationRepository($store))->getById('instrumentation', $observation->id())->outcome());
        }
        $fixture->setAllowed(false);
        try {
            $fixture->record($observation);
            self::fail('Expected revoked retry rejection.');
        } catch (RecordingDenied $exception) {
            self::assertSame('Observation recording is not permitted.', $exception->getMessage());
        }
        self::assertSame(4, $fixture->admissions());
        self::assertSame(1, $this->rowCount($connection, 'observations'));
        self::assertSame(1, $events);
        // Same UUID belongs to a different producer's independent retry namespace.
        $other = ObservationFixture::session(['producer_id' => 'other-producer']);
        $store->commitTransactional(static fn () => (new DbalObservationRepository($store))->add($other->recorded(new DateTimeImmutable('now'))));
        self::assertSame('other-producer', (new DbalObservationRepository($store))->getById('other-producer', $observation->id())->producerId());
        $caseDistinct = ObservationFixture::session(['producer_id' => 'Other-producer']);
        $store->commitTransactional(static fn () => (new DbalObservationRepository($store))->add($caseDistinct->recorded(new DateTimeImmutable('now'))));
        self::assertSame('Other-producer', (new DbalObservationRepository($store))->getById('Other-producer', $observation->id())->producerId());
        self::assertSame(3, $this->rowCount($connection, 'observations'));
    }

    #[DataProvider('databases')]
    public function test_that_current_session_phase_and_parent_scope_is_not_granted_by_existing_records(string $backend): void
    {
        $connection = $this->database($backend);
        $store = new DbalRecordingTransaction($connection);
        $fixture = new RecordingFixture($store);
        $session = ObservationFixture::session();
        $phase = ObservationFixture::phase();
        $fixture->record($session);
        $fixture->record($phase);
        $child = ObservationFixture::phase([
            'observation_id' => ObservationId::generate()->toString(),
            'phase' => PhaseReference::fromStrings('session', 'child', 'phase', 'agent', 'work', 1)->toArray(),
        ]);
        $fixture->setPermittedSessions([]);
        try {
            $fixture->record($session);
            self::fail('Existing session does not bypass current scope.');
        } catch (RecordingDenied) {
            self::assertSame(2, $this->rowCount($connection, 'observations'));
        }
        $fixture->setPermittedSessions(['session']);
        $fixture->setPermittedPhases(['child']);
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
        $fixture->setPermittedPhases(['phase', 'child']);
        $fixture->record($child);
        self::assertSame(3, $this->rowCount($connection, 'observations'));
    }

    #[DataProvider('databases')]
    public function test_that_cross_scope_and_inconsistent_relationships_reject_without_partial_writes(string $backend): void
    {
        $connection = $this->database($backend);
        $store = new DbalRecordingTransaction($connection);
        $fixture = new RecordingFixture($store);
        $fixture->record(ObservationFixture::session());
        $fixture->record(ObservationFixture::phase());
        $otherSession = SessionReference::fromStrings('project', 'other-session', 'build:other');
        $fixture->record(ObservationFixture::session(['observation_id' => ObservationId::generate()->toString(), 'session' => $otherSession->toArray()]));
        $otherProject = SessionReference::fromStrings('concealed', 'session', 'build:concealed');
        $store->commitTransactional(static fn () => (new DbalSessionRepository($store))->add(new Session($otherProject)));
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
        $store = new DbalRecordingTransaction($connection);
        $fixture = new RecordingFixture($store);
        $events = 0;
        $fixture->events()->addHandler(ClassName::underscore(ObservationRecorded::class), static function () use (&$events): void { ++$events; });
        $this->failureTrigger($connection, $backend);
        try {
            $fixture->record(ObservationFixture::session());
            self::fail('Expected late insert failure.');
        } catch (ObservationStorageFailed $exception) {
            self::assertFalse($exception->outcomeUncertain());
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
            self::assertFalse($exception->outcomeUncertain());
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
        $store = new DbalRecordingTransaction($connection);
        $fixture = new RecordingFixture($store);
        $observation = ObservationFixture::session();
        $subscribers = [];
        $fixture->events()->addHandler(ClassName::underscore(ObservationRecorded::class), static function (EventMessage $message) use (&$subscribers, $otherConnection, $connection): void {
            self::assertFalse($connection->isTransactionActive());
            self::assertSame(1, (int) $otherConnection->fetchOne('SELECT COUNT(*) FROM fight_execution_observations'));
            self::assertSame(1, (int) $otherConnection->fetchOne('SELECT COUNT(*) FROM fight_execution_sessions'));
            self::assertSame(['observation_id', 'producer_id', 'project_id', 'session_id', 'phase_id', 'recorded_at'], array_keys($message->payload()->toArray()));
            $subscribers[] = 'failed';
            throw new RuntimeException('private subscriber detail');
        });
        $fixture->events()->addHandler(ClassName::underscore(ObservationRecorded::class), static function () use (&$subscribers): void { $subscribers[] = 'completed'; });
        try {
            $fixture->record($observation);
            self::fail('Expected explicitly committed event failure.');
        } catch (CommittedObservationDispatchFailed $exception) {
            self::assertInstanceOf(EventDispatchFailed::class, $exception->getPrevious());
            self::assertSame('Observation committed but event dispatch failed.', $exception->getMessage());
            self::assertSame([], get_object_vars($exception));
            self::assertTrue($observation->id()->equals($exception->event()->id()));
        }
        self::assertSame(['failed', 'completed'], $subscribers);
        self::assertNotNull((new DbalObservationRepository($store))->getById('instrumentation', $observation->id()));
        $fixture->record($observation);
        self::assertSame(['failed', 'completed'], $subscribers);
        self::assertSame(2, $fixture->admissions());
    }

    #[DataProvider('databases')]
    public function test_that_caller_work_is_not_committed_or_rolled_back_by_recording(string $backend): void
    {
        $connection = $this->database($backend);
        $store = new DbalRecordingTransaction($connection);
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
            self::assertSame(0, $fixture->admissions());
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
        $store = new DbalRecordingTransaction($proxy);
        $fixture = new RecordingFixture($store);
        $events = 0;
        $fixture->events()->addHandler(ClassName::underscore(ObservationRecorded::class), static function () use (&$events): void { ++$events; });
        $observation = ObservationFixture::session();
        try {
            $fixture->record($observation);
            self::fail('Expected uncertain outcome, not a false rollback claim.');
        } catch (ObservationStorageFailed $exception) {
            self::assertSame([], get_object_vars($exception));
            self::assertTrue($exception->outcomeUncertain());
        }
        self::assertTrue($store->isClosed());
        self::assertSame(0, $events);
        $reopened = new DbalRecordingTransaction($this->connect($connection->getParams()));
        self::assertSame($observation->toArray(), (new DbalObservationRepository($reopened))->getById('instrumentation', $observation->id())->toArray());
        $freshFixture = new RecordingFixture($reopened);
        $freshFixture->events()->addHandler(ClassName::underscore(ObservationRecorded::class), static function (): void { self::fail('Matching uncertain retry must not replay an event.'); });
        $freshFixture->record($observation);
        self::assertSame(1, $freshFixture->admissions());
        self::assertSame(1, $this->rowCount($connection, 'observations'));
    }

    #[DataProvider('races')]
    public function test_that_independent_competing_retries_serialize_and_compare_content(string $backend, bool|string $phase, bool $conflict): void
    {
        $connection = $this->database($backend);
        $fixture = new RecordingFixture(new DbalRecordingTransaction($connection));
        if ($phase) { $fixture->record(ObservationFixture::session()); }
        $activity = is_string($phase);
        if ($activity) { $fixture->record(ObservationFixture::phase()); }
        $observation = $activity ? ObservationFixture::activity() : ($phase ? ObservationFixture::phase() : ObservationFixture::session());
        $directory = $this->directory();
        $config = $directory.'/config.json';
        file_put_contents($config, json_encode(['params' => $connection->getParams(), 'observation' => $observation->toArray(), 'conflict' => $conflict, 'usage_conflict' => $phase === 'usage'], JSON_THROW_ON_ERROR));
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
            self::assertSame($observation->id()->toString(), $retry['id']);
            self::assertSame($observation->toArray(), $retry['payload']);
            self::assertSame($activity ? 3 : ($phase ? 2 : 1), $this->rowCount($connection, 'observations'));
            self::assertSame(1, $this->rowCount($connection, 'sessions'));
            self::assertSame($phase ? 1 : 0, $this->rowCount($connection, 'phases'));
        } finally {
            file_put_contents($directory.'/release', 'release');
            $first->stop();
            $second->stop();
        }
    }

    #[DataProvider('databases')]
    public function test_that_activity_and_usage_roundtrip_recheck_admission_and_conflict_without_overwrite(string $backend): void
    {
        $connection = $this->database($backend);
        $store = new DbalRecordingTransaction($connection);
        $fixture = new RecordingFixture($store);
        $fixture->record(ObservationFixture::session());
        $fixture->record(ObservationFixture::phase());
        $events = 0;
        $fixture->events()->addHandler(ClassName::underscore(ObservationRecorded::class), static function () use (&$events): void { ++$events; });
        $observation = ObservationFixture::activity();
        $fixture->bus()->execute(RecordPhaseObservation::fromArray($observation->toArray()));
        $repository = new DbalObservationRepository(new DbalRecordingTransaction($this->connect($connection->getParams())));
        $saved = $repository->getById('instrumentation', $observation->id());
        self::assertSame($observation->toArray(), $saved->toArray());
        self::assertSame('2026-10-20T12:34:56.120Z', $saved->occurrence()->toString());
        self::assertSame('120000', $saved->occurredAt()->format('u'));
        self::assertSame('model:one', $saved->activity()->modelId());
        self::assertSame('agent', $saved->phase()->agentId());
        self::assertSame('instrumentation', $saved->producerId());
        $usage = $saved->activity()->usage();
        self::assertNull($usage[0]->value());
        self::assertSame('unknown', $usage[0]->completeness()->value);
        self::assertNull($usage[1]->value());
        self::assertSame('unavailable', $usage[1]->completeness()->value);
        self::assertSame('0', $usage[2]->value());
        self::assertSame('12.5', $usage[3]->value());
        self::assertSame('partial', $usage[3]->completeness()->value);
        self::assertSame('response', $usage[3]->provenance());
        self::assertSame('token', $usage[3]->unit());
        $fixture->record(Observation::fromArray($observation->toArray()));
        self::assertEquals($saved->recordedAt(), $repository->getById('instrumentation', $observation->id())->recordedAt());
        foreach (['summary', 'kind', 'model_id', 'value', 'unit', 'provenance', 'completeness', 'precision'] as $field) {
            $data = $observation->toArray();
            if ($field === 'summary') { $data['activity']['summary'] = 'Different safe summary'; }
            elseif ($field === 'kind') { $data['activity']['kind'] = 'consumer/other'; }
            elseif ($field === 'model_id') { $data['activity']['model_id'] = 'model:two'; }
            elseif ($field === 'precision') { $data['occurred_at'] = '2026-10-20T12:34:56.120000Z'; }
            elseif ($field === 'completeness') { $data['activity']['usage'][3]['completeness'] = 'complete'; }
            else { $data['activity']['usage'][2][$field] = $field === 'value' ? '1' : 'different'; }
            try { $fixture->record(Observation::fromArray($data)); self::fail('Expected changed-content conflict.'); }
            catch (ObservationConflict $exception) { self::assertSame('Observation identity has conflicting content.', $exception->getMessage()); }
            self::assertSame($saved->comparison(), $repository->getById('instrumentation', $observation->id())->comparison());
        }
        $fixture->setAllowed(false);
        try { $fixture->record($observation); self::fail('Expected revoked retry.'); }
        catch (RecordingDenied) { self::assertSame(1, $events); }
        self::assertSame(3, $this->rowCount($connection, 'observations'));
        self::assertSame(1, $this->rowCount($connection, 'phases'));
        // Retained-record guarantee ends after deliberate removal of its identity/comparison row.
        $fixture->setAllowed(true);
        $connection->delete('fight_execution_observations', ['producer_id' => 'instrumentation', 'observation_id' => $observation->id()->toString()]);
        $fixture->record($observation);
        self::assertSame(2, $events);
    }

    #[DataProvider('databases')]
    public function test_that_activity_missing_or_cross_scope_phase_rejects_without_creating_subjects(string $backend): void
    {
        $connection = $this->database($backend);
        $fixture = new RecordingFixture(new DbalRecordingTransaction($connection));
        $fixture->record(ObservationFixture::session());
        $activity = ObservationFixture::activity();
        try { $fixture->record($activity); self::fail('Activity must not create its phase.'); }
        catch (InvalidObservationRelationship) { self::assertSame(0, $this->rowCount($connection, 'phases')); }
        $fixture->record(ObservationFixture::phase());
        foreach ([
            ['phase' => PhaseReference::fromStrings('session', 'missing', null, 'agent', 'review', 1)->toArray()],
            ['phase' => PhaseReference::fromStrings('session', 'phase', null, 'other-agent', 'review', 1)->toArray()],
            ['session' => SessionReference::fromStrings('project', 'other', 'build:other')->toArray(), 'phase' => PhaseReference::fromStrings('other', 'phase', null, 'agent', 'review', 1)->toArray()],
        ] as $changes) {
            try { $fixture->record(ObservationFixture::activity($changes)); self::fail('Expected relationship rejection.'); }
            catch (InvalidObservationRelationship $exception) { self::assertSame('Observation relationship is unavailable or inconsistent.', $exception->getMessage()); }
        }
        foreach (['concealed', 'unknown'] as $project) {
            try { $fixture->record(ObservationFixture::activity(['session' => SessionReference::fromStrings($project, 'session', 'build:147')->toArray()])); self::fail('Expected scope denial.'); }
            catch (RecordingDenied $exception) { self::assertSame('Observation recording is not permitted.', $exception->getMessage()); }
        }
        $fixture->setPermittedPhases([]);
        try { $fixture->record($activity); self::fail('Expected current phase denial.'); }
        catch (RecordingDenied) { self::assertSame(2, $this->rowCount($connection, 'observations')); }
        self::assertSame(1, $this->rowCount($connection, 'sessions'));
        self::assertSame(1, $this->rowCount($connection, 'phases'));
    }

    #[DataProvider('databases')]
    public function test_that_activity_usage_and_retry_identity_roll_back_after_insert_and_commit_before_subscribers(string $backend): void
    {
        $connection = $this->database($backend);
        $store = new DbalRecordingTransaction($connection);
        $fixture = new RecordingFixture($store);
        $fixture->record(ObservationFixture::session());
        $fixture->record(ObservationFixture::phase());
        if ($backend === 'sqlite') {
            $connection->executeStatement("CREATE TRIGGER fight_execution_fail AFTER INSERT ON fight_execution_observations BEGIN SELECT RAISE(ABORT, 'injected'); END");
        } elseif ($backend === 'mysql') {
            // The ordinary test user cannot create triggers under MySQL binary logging.
            // Forward a real insert, then inject a real failing statement before commit.
            $inject = true;
            $proxy = $this->mock(Connection::class);
            foreach (['getDatabasePlatform', 'isTransactionActive', 'beginTransaction', 'executeStatement', 'fetchOne', 'fetchAssociative', 'commit', 'rollBack'] as $method) {
                $proxy->shouldReceive($method)->andReturnUsing(static fn (...$arguments) => $connection->$method(...$arguments));
            }
            $proxy->shouldReceive('insert')->andReturnUsing(static function (...$arguments) use ($connection, &$inject): int {
                $result = $connection->insert(...$arguments);
                if ($inject) { $connection->executeStatement('INSERT INTO absent_activity_failure_table (id) VALUES (1)'); }
                return $result;
            });
            $store = new DbalRecordingTransaction($proxy);
            $fixture = new RecordingFixture($store);
        } else {
            $connection->executeStatement("CREATE FUNCTION fight_execution_fail() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'injected'; END; $$");
            $connection->executeStatement('CREATE TRIGGER fight_execution_fail AFTER INSERT ON fight_execution_observations FOR EACH ROW EXECUTE FUNCTION fight_execution_fail()');
        }
        $calls = 0;
        $other = $this->connect($connection->getParams());
        $fixture->events()->addHandler(ClassName::underscore(ObservationRecorded::class), static function (EventMessage $message) use (&$calls, $other, $connection): void {
            ++$calls;
            self::assertFalse($connection->isTransactionActive());
            self::assertSame(3, (int) $other->fetchOne('SELECT COUNT(*) FROM fight_execution_observations'));
            self::assertSame(['observation_id', 'producer_id', 'project_id', 'session_id', 'phase_id', 'recorded_at'], array_keys($message->payload()->toArray()));
            throw new RuntimeException('private subscriber diagnostic');
        });
        $observation = ObservationFixture::activity();
        try { $fixture->record($observation); self::fail('Expected after-insert rollback.'); }
        catch (ObservationStorageFailed $exception) { self::assertFalse($exception->outcomeUncertain()); }
        self::assertSame(0, $calls);
        self::assertNull((new DbalObservationRepository($store))->getById('instrumentation', $observation->id()));
        self::assertSame(2, $this->rowCount($connection, 'observations'));
        if ($backend === 'mysql') { $inject = false; }
        else { $this->dropFailureTrigger($connection, $backend); }
        try { $fixture->record($observation); self::fail('Expected committed subscriber failure.'); }
        catch (CommittedObservationDispatchFailed $exception) {
            self::assertSame('Observation committed but event dispatch failed.', $exception->getMessage());
            self::assertSame($observation->id()->toString(), $exception->event()->id()->toString());
        }
        $saved = (new DbalObservationRepository($store))->getById('instrumentation', $observation->id());
        self::assertSame($observation->comparison(), $saved->comparison());
        $fixture->record($observation);
        self::assertSame(1, $calls);
        self::assertSame(3, $this->rowCount($connection, 'observations'));
    }

    #[DataProvider('databases')]
    public function test_that_current_installation_preserves_evidence_and_recording_comparison_agrees_with_sql(string $backend): void
    {
        $connection = $this->database($backend);
        $manager = $connection->createSchemaManager();
        $store = new DbalRecordingTransaction($connection);
        $fixture = new RecordingFixture($store);
        $fixture->record(ObservationFixture::session());
        $fixture->record(ObservationFixture::phase());
        $fixture->record(ObservationFixture::activity());
        $before = $connection->fetchAllAssociative('SELECT * FROM fight_execution_observations ORDER BY observation_id');
        (new DbalObservationSchema())->install($connection);
        (new DbalObservationSchema())->install($connection);
        self::assertSame(1, (int) $connection->fetchOne('SELECT version FROM fight_execution_schema WHERE singleton = 1'));
        self::assertTrue($manager->introspectTable('fight_execution_observations')->hasIndex('fight_execution_recording_order'));
        self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM fight_execution_observations ORDER BY observation_id'));
        $repository = new DbalObservationRepository($store);
        // Adapter conformance supplies controlled recorded times, not producer-controlled Command receipt times.
        $keys = [
            ['019a1234-5678-4abc-8def-000000000001', '000001', '2026-10-20T12:00:00Z'],
            ['019a1234-5678-4abc-8def-000000000002', '000001', '2020-01-01T00:00:00.1Z'],
            ['019a1234-5678-4abc-8dff-000000000000', '000001', '2026-10-20T12:00:00.123456Z'],
            ['019a1234-5679-4000-8000-000000000000', '000001', '2026-10-20T12:00:00.00Z'],
            ['019a1234-5678-4abc-8def-000000000000', '000002', '2000-01-01T00:00:00Z'],
        ];
        foreach (array_reverse($keys) as [$id, $micros, $occurrence]) {
            $root = ObservationFixture::activity(['observation_id' => $id, 'occurred_at' => $occurrence])->recorded(new DateTimeImmutable('2026-10-22T00:00:00.'.$micros.'Z'));
            $store->commitTransactional(static fn () => $repository->add($root));
            self::assertSame($occurrence, $repository->getById('instrumentation', $root->id())->occurrence()->toString());
        }
        $rows = $connection->fetchAllAssociative('SELECT producer_id, observation_id FROM fight_execution_observations ORDER BY recorded_at DESC, observation_id DESC');
        $roots = array_map(static fn (array $row) => $repository->getById($row['producer_id'], ObservationId::fromString($row['observation_id'])), $rows);
        $application = array_reverse($roots);
        usort($application, static fn (Observation $a, Observation $b): int => $b->compareRecordingTo($a));
        self::assertSame(array_map(static fn ($root) => $root->id()->toString(), $roots), array_map(static fn ($root) => $root->id()->toString(), $application));
        self::assertSame(array_reverse(array_column($keys, 0)), array_slice(array_column($rows, 'observation_id'), 0, 5));
        for ($i = 1; $i < count($roots); ++$i) { self::assertSame(1, $roots[$i - 1]->compareRecordingTo($roots[$i])); }
        // Ordinary recording uses the same current schema and pipeline.
        (new RecordingFixture($store))->record(ObservationFixture::session(['observation_id' => ObservationId::generate()->toString()]));
    }

    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; }
    }

    public static function races(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) {
            foreach ([false, true, 'activity', 'usage'] as $phase) {
                foreach ([false, true] as $conflict) {
                    yield $backend.'-'.(is_string($phase) ? $phase : ($phase ? 'phase' : 'session')).'-'.($conflict ? 'conflict' : 'matching') => [$backend, $phase, $conflict];
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
            $id = $phase ? ObservationFixture::phase()->id() : ObservationFixture::session()->id();
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
