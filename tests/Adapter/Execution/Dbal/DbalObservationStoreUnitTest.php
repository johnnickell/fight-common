<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Execution\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Fight\Common\Adapter\Execution\Dbal\DbalObservationSchema;
use Fight\Common\Adapter\Execution\Dbal\DbalObservationStore;
use Fight\Common\Application\Execution\ObservationStorageFailed;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\ObservationId;
use Fight\Test\Common\TestCase\Execution\ObservationFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(DbalObservationStore::class)]
#[CoversClass(DbalObservationSchema::class)]
final class DbalObservationStoreUnitTest extends UnitTestCase
{
    public function test_that_owned_transactions_persist_complete_references_and_precise_evidence(): void
    {
        $connection = $this->database();
        $store = new DbalObservationStore($connection);
        self::assertFalse($store->isClosed());
        self::assertNull($store->get('instrumentation', ObservationId::generate()));
        self::assertNull($store->session('project', 'missing'));
        self::assertNull($store->phase('project', 'missing'));
        $session = ObservationFixture::session();
        $phase = ObservationFixture::phase();
        $first = $store->commitTransactional(static function () use ($store, $session) {
            $store->addSession($session->session);
            return $store->append($session);
        });
        $second = $store->commitTransactional(static function () use ($store, $phase) {
            $store->addPhase('project', $phase->phase);
            return $store->append($phase);
        });
        foreach ([$first, $second] as $recorded) {
            $reconstructed = $store->get('instrumentation', $recorded->observation->id);
            self::assertSame($recorded->observation->toArray(), $reconstructed->observation->toArray());
            self::assertSame($recorded->recordedAt->format('Y-m-d\TH:i:s.u\Z'), $reconstructed->recordedAt->format('Y-m-d\TH:i:s.u\Z'));
            self::assertNotEquals($recorded->observation->occurredAt, $reconstructed->recordedAt);
        }
        self::assertSame($session->session->toArray(), $store->session('project', 'session')->toArray());
        self::assertSame($phase->phase->toArray(), $store->phase('project', 'phase')->toArray());
        self::assertFalse($connection->isTransactionActive());
        self::assertSame(1, (int) $connection->fetchOne('PRAGMA foreign_keys'));
    }

    public function test_that_write_methods_reject_unowned_and_caller_owned_transactions(): void
    {
        $connection = $this->database();
        $store = new DbalObservationStore($connection);
        foreach ([false, true] as $outer) {
            if ($outer) {
                $connection->beginTransaction();
                $connection->executeStatement('CREATE TABLE caller_work (id INTEGER)');
                $connection->insert('caller_work', ['id' => 42]);
            }
            foreach ([
                static fn () => $store->addSession(ObservationFixture::session()->session),
                static fn () => $store->addPhase('project', ObservationFixture::phase()->phase),
                static fn () => $store->append(ObservationFixture::session()),
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

    public function test_that_a_late_database_failure_rolls_back_all_related_writes_and_remains_reusable(): void
    {
        $connection = $this->database();
        $store = new DbalObservationStore($connection);
        $observation = ObservationFixture::session();
        try {
            $store->commitTransactional(static function () use ($store, $connection, $observation): void {
                $store->addSession($observation->session);
                $store->append($observation);
                $connection->executeStatement('INSERT INTO absent_failure_table (id) VALUES (1)');
            });
            self::fail('Expected explicit rolled-back storage failure.');
        } catch (ObservationStorageFailed $exception) {
            self::assertFalse($exception->outcomeUncertain);
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
        $store = new DbalObservationStore($connection);
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
        $store = new DbalObservationStore($connection);
        try {
            $store->commitTransactional(static function () use ($stage, $failure) {
                if ($stage === 'rollback') { throw $failure; }
                return null;
            });
            self::fail('Expected uncertainty.');
        } catch (ObservationStorageFailed $exception) {
            self::assertTrue($exception->outcomeUncertain);
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
            new DbalObservationStore($connection);
            self::fail('Expected unsupported platform.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('SQLite, MySQL and PostgreSQL', $exception->getMessage());
        }
        $sqlite = $this->database();
        $sqlite->beginTransaction();
        try {
            new DbalObservationStore($sqlite);
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
        self::assertFalse((new DbalObservationStore($connection))->isClosed());
    }

    public function test_that_unsupported_stored_payload_and_schema_versions_reject_clearly(): void
    {
        $connection = $this->database();
        $store = new DbalObservationStore($connection);
        $observation = ObservationFixture::session();
        $store->commitTransactional(static function () use ($store, $observation): void {
            $store->addSession($observation->session);
            $store->append($observation);
        });
        foreach ([['version' => 2], ['version' => 1, 'payload' => 'null'], ['payload' => '{"version":2}']] as $changes) {
            $connection->update('fight_execution_observations', $changes, ['producer_id' => 'instrumentation']);
            try {
                $store->get('instrumentation', $observation->id);
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
        self::assertTrue($connection->createSchemaManager()->tablesExist(DbalObservationSchema::TABLES));
        $definition = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])->createSchemaManager()->introspectSchema();
        $schema->addTo($definition, new MySQLPlatform());
        foreach (DbalObservationSchema::TABLES as $table) {
            self::assertSame('utf8mb4_bin', $definition->getTable($table)->getOption('collation'));
        }
        self::assertCount(2, $definition->getTable('fight_execution_observations')->getForeignKeys());
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

    private function database(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new DbalObservationSchema())->install($connection);
        return $connection;
    }
}
