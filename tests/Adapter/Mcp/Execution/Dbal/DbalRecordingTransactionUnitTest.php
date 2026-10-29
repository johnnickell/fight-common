<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Execution\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalObservationRepository;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalObservationSchema;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalPhaseRepository;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalRecordingTransaction;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalSessionRepository;
use Fight\Common\Application\Mcp\Execution\Observation\Exception\ObservationStorageFailed;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Observation\ObservationId;
use Fight\Common\Domain\Mcp\Phase\Phase;
use Fight\Common\Domain\Mcp\Session\Session;
use Fight\Test\Common\TestCase\Mcp\Execution\ObservationFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(DbalRecordingTransaction::class)]
#[CoversClass(DbalSessionRepository::class)]
#[CoversClass(DbalPhaseRepository::class)]
#[CoversClass(DbalObservationRepository::class)]
#[CoversClass(DbalObservationSchema::class)]
final class DbalRecordingTransactionUnitTest extends UnitTestCase
{
    public function test_that_owned_transactions_persist_complete_references_and_precise_evidence(): void
    {
        $connection = $this->database();
        $store = new DbalRecordingTransaction($connection);
        self::assertFalse($store->isClosed());
        self::assertNull((new DbalObservationRepository($store))->getById('instrumentation', ObservationId::generate()));
        self::assertNull((new DbalSessionRepository($store))->getById('project', 'missing'));
        self::assertNull((new DbalPhaseRepository($store))->getById('project', 'missing'));
        $session = ObservationFixture::session();
        $phase = ObservationFixture::phase();
        $first = $session->recorded(new DateTimeImmutable('2026-10-21T12:00:00.000001Z'));
        $second = $phase->recorded(new DateTimeImmutable('2026-10-21T12:00:00.000002Z'));
        $store->commitTransactional(static function () use ($store, $first): void {
            (new DbalSessionRepository($store))->add(new Session($first->session()));
            (new DbalObservationRepository($store))->add($first);
        });
        $store->commitTransactional(static function () use ($store, $second): void {
            (new DbalPhaseRepository($store))->add(new Phase('project', $second->phase()));
            (new DbalObservationRepository($store))->add($second);
        });
        foreach ([$first, $second] as $recorded) {
            $reconstructed = (new DbalObservationRepository($store))->getById('instrumentation', $recorded->id());
            self::assertSame($recorded->toArray(), $reconstructed->toArray());
            self::assertSame($recorded->recordedAt()->format('Y-m-d\TH:i:s.u\Z'), $reconstructed->recordedAt()->format('Y-m-d\TH:i:s.u\Z'));
            self::assertNotEquals($recorded->occurredAt(), $reconstructed->recordedAt());
        }
        self::assertSame($session->session()->toArray(), (new DbalSessionRepository($store))->getById('project', 'session')->reference()->toArray());
        self::assertSame($phase->phase()->toArray(), (new DbalPhaseRepository($store))->getById('project', 'phase')->reference()->toArray());
        self::assertFalse($connection->isTransactionActive());
        self::assertSame(1, (int) $connection->fetchOne('PRAGMA foreign_keys'));
    }

    public function test_that_write_methods_reject_unowned_and_caller_owned_transactions(): void
    {
        $connection = $this->database();
        $store = new DbalRecordingTransaction($connection);
        self::assertSame($connection, $store->connection());
        foreach ([false, true] as $outer) {
            if ($outer) {
                $connection->beginTransaction();
                $connection->executeStatement('CREATE TABLE caller_work (id INTEGER)');
                $connection->insert('caller_work', ['id' => 42]);
            }
            foreach ([
                static fn () => (new DbalSessionRepository($store))->add(new Session(ObservationFixture::session()->session())),
                static fn () => (new DbalPhaseRepository($store))->add(new Phase('project', ObservationFixture::phase()->phase())),
                static fn () => (new DbalObservationRepository($store))->add(ObservationFixture::session()->recorded(new DateTimeImmutable('now'))),
                static fn () => $store->commitTransactional(static fn () => $store->commitTransactional(static fn () => null)),
            ] as $write) {
                try {
                    $write();
                    self::fail('Expected transaction ownership rejection.');
                } catch (LogicException) {
                    self::assertSame($outer, $connection->isTransactionActive());
                }
            }
            if ($outer) {
                self::assertSame(42, (int) $connection->fetchOne('SELECT id FROM caller_work'));
                $connection->rollBack();
            }
        }
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_execution_sessions'));
        self::assertFalse($store->isClosed());
    }

    public function test_that_unrecorded_evidence_cannot_be_persisted_with_a_fabricated_receipt(): void
    {
        $connection = $this->database();
        $transaction = new DbalRecordingTransaction($connection);
        try {
            $transaction->commitTransactional(static function () use ($transaction): void {
                (new DbalSessionRepository($transaction))->add(new Session(ObservationFixture::session()->session()));
                (new DbalObservationRepository($transaction))->add(ObservationFixture::session());
            });
            self::fail('Expected unrecorded root rejection.');
        } catch (DomainException $exception) {
            self::assertSame('Observation has not been recorded.', $exception->getMessage());
        }
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_execution_sessions'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_execution_observations'));
        self::assertFalse($connection->isTransactionActive());
    }

    public function test_that_repositories_from_another_boundary_cannot_join_or_commit_the_recording_transaction(): void
    {
        $connection = $this->database();
        $owner = new DbalRecordingTransaction($connection);
        $other = new DbalRecordingTransaction($connection);
        try {
            $owner->commitTransactional(static function () use ($owner, $other): void {
                (new DbalSessionRepository($owner))->add(new Session(ObservationFixture::session()->session()));
                (new DbalPhaseRepository($other))->add(new Phase('project', ObservationFixture::phase()->phase()));
            });
            self::fail('Expected boundary mismatch rejection.');
        } catch (LogicException $exception) {
            self::assertSame('Observation writes require the recording-owned transaction.', $exception->getMessage());
        }
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_execution_sessions'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_execution_phases'));
        self::assertFalse($connection->isTransactionActive());
    }

    public function test_that_a_late_database_failure_rolls_back_all_related_writes_and_remains_reusable(): void
    {
        $connection = $this->database();
        $store = new DbalRecordingTransaction($connection);
        $observation = ObservationFixture::session();
        try {
            $store->commitTransactional(static function () use ($store, $connection, $observation): void {
                (new DbalSessionRepository($store))->add(new Session($observation->session()));
                (new DbalObservationRepository($store))->add($observation->recorded(new DateTimeImmutable('now')));
                $connection->executeStatement('INSERT INTO absent_failure_table (id) VALUES (1)');
            });
            self::fail('Expected explicit rolled-back storage failure.');
        } catch (ObservationStorageFailed $exception) {
            self::assertFalse($exception->outcomeUncertain());
            self::assertInstanceOf(Exception::class, $exception->getPrevious());
        }
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_execution_sessions'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_execution_observations'));
        self::assertFalse($store->isClosed());
        self::assertSame('committed', $store->commitTransactional(static fn (): string => 'committed'));
    }

    public function test_that_domain_rejection_rolls_back_without_becoming_a_storage_failure(): void
    {
        $connection = $this->database();
        $store = new DbalRecordingTransaction($connection);
        $failure = new DomainException('safe rejection');
        try {
            $store->commitTransactional(static function () use ($failure) { throw $failure; });
            self::fail('Expected rejection.');
        } catch (DomainException $exception) {
            self::assertSame($failure, $exception);
        }
        self::assertFalse($connection->isTransactionActive());
    }

    #[DataProvider('uncertainFailures')]
    public function test_that_begin_commit_and_rollback_uncertainty_close_the_store(string $stage, bool $activeAfterCommit): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->once()->andReturn(new PostgreSQLPlatform());
        // The initial ownership check is idle; subsequent checks model lost acknowledgment.
        $connection->shouldReceive('isTransactionActive')->andReturn(false, $activeAfterCommit);
        $failure = new RuntimeException('connection lost');
        if ($stage === 'begin') {
            $connection->shouldReceive('beginTransaction')->once()->andThrow($failure);
        } else {
            $connection->shouldReceive('beginTransaction')->once();
            $connection->shouldReceive('executeStatement')->once();
            $connection->shouldReceive('fetchOne')->once()->andReturn(1);
            if ($stage === 'rollback') {
                $connection->shouldReceive('rollBack')->once()->andThrow(new RuntimeException('rollback unavailable'));
            } else {
                $connection->shouldReceive('commit')->once()->andThrow($failure);
                if ($activeAfterCommit) {
                    $connection->shouldReceive('rollBack')->once();
                }
            }
        }
        $store = new DbalRecordingTransaction($connection);
        try {
            $store->commitTransactional(static function () use ($stage, $failure) {
                if ($stage === 'rollback') { throw $failure; }
                return null;
            });
            self::fail('Expected uncertainty.');
        } catch (ObservationStorageFailed $exception) {
            self::assertTrue($exception->outcomeUncertain());
            self::assertSame($failure, $exception->getPrevious());
        }
        self::assertTrue($store->isClosed());
        $this->expectException(LogicException::class);
        $store->commitTransactional(static fn () => null);
    }

    public static function uncertainFailures(): iterable
    {
        yield ['begin', false];
        yield ['commit', false];
        yield ['commit', true];
        yield ['rollback', false];
    }

    public function test_that_unsupported_platform_and_sqlite_outer_composition_reject(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->once()->andReturn(new OraclePlatform());
        try {
            new DbalRecordingTransaction($connection);
            self::fail('Expected unsupported platform.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('SQLite, MySQL and PostgreSQL', $exception->getMessage());
        }
        $sqlite = $this->database();
        $sqlite->beginTransaction();
        try {
            new DbalRecordingTransaction($sqlite);
            self::fail('Expected outer composition rejection.');
        } catch (LogicException) {
            self::assertTrue($sqlite->isTransactionActive());
        } finally {
            $sqlite->rollBack();
        }
    }

    public function test_that_mysql_is_an_accepted_platform(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->once()->andReturn(new MySQLPlatform());
        self::assertFalse((new DbalRecordingTransaction($connection))->isClosed());
    }

    public function test_that_unsupported_stored_payload_and_schema_versions_reject_clearly(): void
    {
        $connection = $this->database();
        $store = new DbalRecordingTransaction($connection);
        $observation = ObservationFixture::session();
        $store->commitTransactional(static function () use ($store, $observation): void {
            (new DbalSessionRepository($store))->add(new Session($observation->session()));
            (new DbalObservationRepository($store))->add($observation->recorded(new DateTimeImmutable('now')));
        });
        foreach ([['version' => 2], ['version' => 1, 'payload' => 'null'], ['payload' => '{"version":2}']] as $changes) {
            $connection->update('fight_execution_observations', $changes, ['producer_id' => 'instrumentation']);
            try {
                (new DbalObservationRepository($store))->getById('instrumentation', $observation->id());
                self::fail('Expected stored version or shape rejection.');
            } catch (DomainException $exception) {
                self::assertStringContainsString('observation', $exception->getMessage());
            }
        }
        $connection->update('fight_execution_schema', ['version' => 2], ['singleton' => 1]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported observation storage version.');
        $store->commitTransactional(static fn () => null);
    }

    public function test_that_schema_composition_preserves_consumer_tables_and_qualifies_mysql_binary_identity(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE consumer_owned (id INTEGER)');
        $connection->insert('consumer_owned', ['id' => 42]);
        $schema = new DbalObservationSchema();
        $schema->install($connection);
        $schema->install($connection);
        self::assertSame(42, (int) $connection->fetchOne('SELECT id FROM consumer_owned'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT version FROM fight_execution_schema WHERE singleton = 1'));
        self::assertTrue($connection->createSchemaManager()->tablesExist(DbalObservationSchema::TABLES));
        $definition = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])->createSchemaManager()->introspectSchema();
        $schema->addTo($definition, new MySQLPlatform());
        foreach (DbalObservationSchema::TABLES as $table) {
            self::assertSame('utf8mb4_bin', $definition->getTable($table)->getOption('collation'));
        }
        self::assertCount(3, $definition->getTable('fight_execution_observations')->getForeignKeys());
    }

    public function test_that_schema_installation_rejects_active_partial_and_unsupported_composition(): void
    {
        foreach (['active', 'partial', 'unsupported'] as $mode) {
            $connection = $this->database();
            if ($mode === 'active') {
                $connection->beginTransaction();
            } elseif ($mode === 'partial') {
                $connection->executeStatement('DROP TABLE fight_execution_observations');
            } else {
                $connection->update('fight_execution_schema', ['version' => 2], ['singleton' => 1]);
            }
            try {
                (new DbalObservationSchema())->install($connection);
                self::fail('Expected schema rejection.');
            } catch (RuntimeException $exception) {
                self::assertSame($mode === 'active', $connection->isTransactionActive());
                self::assertStringContainsString('schema', $exception->getMessage());
            } finally {
                if ($connection->isTransactionActive()) { $connection->rollBack(); }
            }
        }
    }

    public function test_that_activity_uses_the_same_atomic_version_one_payload(): void
    {
        $connection = $this->database();
        $store = new DbalRecordingTransaction($connection);
        $activity = ObservationFixture::activity()->recorded(new DateTimeImmutable('2026-10-21T00:00:00.000001Z'));
        $store->commitTransactional(static function () use ($store, $activity): void {
            (new DbalSessionRepository($store))->add(new Session($activity->session()));
            (new DbalPhaseRepository($store))->add(new Phase('project', $activity->phase()));
            (new DbalObservationRepository($store))->add($activity);
        });
        $saved = (new DbalObservationRepository($store))->getById('instrumentation', $activity->id());
        self::assertSame($activity->comparison(), $saved->comparison());
        self::assertSame('2026-10-20T12:34:56.120Z', $saved->occurrence()->toString());
        self::assertEquals($activity->recordedAt(), $saved->recordedAt());
        self::assertSame(1, (int) $connection->fetchOne('SELECT version FROM fight_execution_observations'));
        self::assertSame(1, $saved->toArray()['version']);
    }

    public function test_that_current_schema_missing_its_order_index_rejects_as_incomplete(): void
    {
        $connection = $this->database();
        $connection->createSchemaManager()->dropIndex('fight_execution_recording_order', 'fight_execution_observations');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Incomplete observation schema version one.');
        (new DbalObservationSchema())->install($connection);
    }

    private function database(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new DbalObservationSchema())->install($connection);
        return $connection;
    }
}
