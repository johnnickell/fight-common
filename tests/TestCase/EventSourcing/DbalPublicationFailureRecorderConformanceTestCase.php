<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\EventSourcing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalEventStore;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalEventStoreSchema;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalEventStoreTables;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationCursorStore;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationCursorStoreSchema;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationFailureRecorder;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationFailureRecorderSchema;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationFailureTables;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use Fight\Common\Application\EventSourcing\EventPublicationRunner;
use Fight\Common\Application\EventSourcing\PublicationFailureRecorder;
use Fight\Common\Domain\EventSourcing\EventMapper;
use Fight\Common\Domain\EventSourcing\EventMapping;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Utility\ClassName;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * Class DbalPublicationFailureRecorderConformanceTestCase
 *
 * Reusable Doctrine DBAL publication-failure recorder contract
 */
abstract class DbalPublicationFailureRecorderConformanceTestCase extends PublicationFailureRecorderConformanceTestCase
{
    private ?Connection $conformanceConnection = null;

    /**
     * Verifies the complete portable failure snapshot is stored in handler order
     */
    public function test_that_record_persists_the_complete_portable_failure_snapshot(): void
    {
        $connection = $this->resetDatabase();
        $recorder = new DbalPublicationFailureRecorder($connection);

        $recorder->record($this->failure());

        self::assertSame(
            [[
                'publication_name'    => 'orders.subscribers',
                'aggregate_name'      => 'order',
                'aggregate_identifier'=> 'order-42',
                'event_name'          => 'orders.order-placed',
                'schema_version'      => 2,
                'stream_version'      => 7,
                'global_position'     => 23,
                'message_id'          => '6ba7b841-9dad-11d1-80b4-00c04fd430c8',
                'dispatch_started_at' => '2026-08-09T09:15:30.123456+00:00',
            ]],
            $connection->fetchAllAssociative(
                'SELECT * FROM publication_failures ORDER BY publication_name, global_position',
            ),
        );
        self::assertSame(
            [
                [
                    'publication_name'    => 'orders.subscribers',
                    'global_position'     => 23,
                    'handler_position'    => 0,
                    'callable_description'=> 'OrdersSubscriber::onOrderPlaced',
                    'exception_class'     => RuntimeException::class,
                    'exception_code'      => 73,
                    'diagnostic_message'  => 'Inventory unavailable.',
                ],
                [
                    'publication_name'    => 'orders.subscribers',
                    'global_position'     => 23,
                    'handler_position'    => 1,
                    'callable_description'=> 'AuditSubscriber::__invoke',
                    'exception_class'     => LogicException::class,
                    'exception_code'      => 91,
                    'diagnostic_message'  => 'Audit unavailable.',
                ],
            ],
            $connection->fetchAllAssociative(
                'SELECT * FROM publication_handler_failures ORDER BY handler_position',
            ),
        );
    }

    /**
     * Verifies recorded aggregate and handler evidence survives reopening
     */
    public function test_that_recorded_failure_evidence_survives_reopening(): void
    {
        $connection = $this->resetDatabase();
        new DbalPublicationFailureRecorder($connection)->record($this->failure());
        $connection->close();
        $connection = $this->createConnection();

        self::assertSame(
            [['orders.subscribers', 23, 'order-42']],
            array_map(
                static fn (array $row): array => [
                    $row['publication_name'],
                    $row['global_position'],
                    $row['aggregate_identifier'],
                ],
                $connection->fetchAllAssociative(
                    <<<'SQL'
                        SELECT publication_name, global_position, aggregate_identifier
                        FROM publication_failures
                        ORDER BY publication_name, global_position
                        SQL,
                ),
            ),
        );
        self::assertSame(
            [
                ['orders.subscribers', 23, 0],
                ['orders.subscribers', 23, 1],
            ],
            array_map(
                static fn (array $row): array => [
                    $row['publication_name'],
                    $row['global_position'],
                    $row['handler_position'],
                ],
                $connection->fetchAllAssociative(
                    <<<'SQL'
                        SELECT publication_name, global_position, handler_position
                        FROM publication_handler_failures
                        ORDER BY publication_name, global_position, handler_position
                        SQL,
                ),
            ),
        );
    }

    /**
     * Verifies aggregate evidence rolls back when handler evidence cannot be stored
     */
    public function test_that_record_is_atomic_when_a_handler_write_fails(): void
    {
        $connection = $this->resetDatabase();
        $connection->executeStatement('DROP TABLE publication_handler_failures');
        $recorder = new DbalPublicationFailureRecorder($connection);

        try {
            $recorder->record($this->failure());
            self::fail('Expected the missing handler-failure table to reject the record.');
        } catch (Exception) {
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM publication_failures'));
        }
    }

    /**
     * Verifies installation is repeatable and independent from event storage
     */
    public function test_that_schema_installation_is_independent_and_idempotent(): void
    {
        $connection = $this->resetDatabase();
        $connection->executeStatement('DROP TABLE IF EXISTS event_store_events');
        $connection->executeStatement('DROP TABLE IF EXISTS event_store_global_position');
        new DbalPublicationFailureRecorderSchema()->install($connection);

        self::assertTrue($connection->createSchemaManager()->tablesExist([
            'publication_failures',
            'publication_handler_failures'
        ]));
        self::assertFalse($connection->createSchemaManager()->tablesExist([
            'event_store_events'
        ]));
    }

    public function test_that_a_later_handler_failure_rolls_back_the_parent_and_earlier_handler(): void
    {
        $connection = $this->createConnection();
        $this->clearPublicationTables($connection);
        $tables = new DbalPublicationFailureTables('Order', 'Group');
        $parentSql = $tables->failureTable()->quoted($connection->getDatabasePlatform());
        $handlerSql = $tables->handlerFailureTable()->quoted($connection->getDatabasePlatform());
        $schema = new DbalPublicationFailureRecorderSchema($tables);
        try {
            $schema->install($connection);
            $connection->executeStatement('DROP TABLE '.$handlerSql);
            $connection->executeStatement('CREATE TABLE '.$handlerSql.' (
                publication_name VARCHAR(255) NOT NULL,
                global_position BIGINT NOT NULL,
                handler_position INTEGER NOT NULL CHECK (handler_position = 0),
                callable_description TEXT NOT NULL,
                exception_class VARCHAR(1024) NOT NULL,
                exception_code BIGINT NOT NULL,
                diagnostic_message TEXT NOT NULL
            )');
            $recorder = new DbalPublicationFailureRecorder($connection, $tables);
            $this->assertRecordingFails($recorder);
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM '.$parentSql));
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM '.$handlerSql));
            $connection->executeStatement('DROP TABLE '.$handlerSql);
            $schema->install($connection);
            $recorder->record($this->failure());
            self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM '.$parentSql));
            self::assertSame(2, $connection->fetchOne('SELECT COUNT(*) FROM '.$handlerSql));
        } finally {
            $this->clearPublicationTables($connection);
        }
    }

    public function test_that_changing_failure_tables_does_not_install_copy_or_fall_back(): void
    {
        $connection = $this->createConnection();
        $this->clearPublicationTables($connection);
        try {
            (new DbalPublicationFailureRecorderSchema())->install($connection);
            new DbalPublicationFailureRecorder(connection: $connection)->record($this->failure());
            $parents = $connection->fetchAllAssociative('SELECT * FROM publication_failures');
            $handlers = $connection->fetchAllAssociative('SELECT * FROM publication_handler_failures');
            $tables = new DbalPublicationFailureTables('Order', 'Group');
            $schema = new DbalPublicationFailureRecorderSchema($tables);
            $schema->schema();
            $this->assertRecordingFails(new DbalPublicationFailureRecorder($connection, $tables));
            $schema->install($connection);
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM '.$tables->failureTable()->quoted($connection->getDatabasePlatform())));
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM '.$tables->handlerFailureTable()->quoted($connection->getDatabasePlatform())));
            self::assertSame($parents, $connection->fetchAllAssociative('SELECT * FROM publication_failures'));
            self::assertSame($handlers, $connection->fetchAllAssociative('SELECT * FROM publication_handler_failures'));
        } finally {
            $this->clearPublicationTables($connection);
        }
    }

    public static function configuredSelections(): iterable
    {
        yield 'all overridden' => ['Order', 'Group'];
        yield 'parent only' => ['Order', 'publication_handler_failures'];
        yield 'handler only' => ['publication_failures', 'Group'];
    }

    #[DataProvider('configuredSelections')]
    public function test_that_selected_storage_is_durable_atomic_idempotent_and_isolated(
        string $failureTable, string $handlerTable,
    ): void {
        $connection = $this->createConnection();
        $this->clearPublicationTables($connection);
        $tables = new DbalPublicationFailureTables($failureTable, $handlerTable);
        $platform = $connection->getDatabasePlatform();
        $parentSql = $tables->failureTable()->quoted($platform);
        $handlerSql = $tables->handlerFailureTable()->quoted($platform);
        $schema = new DbalPublicationFailureRecorderSchema(tables: $tables);

        try {
            $schema->schema();
            $recorder = new DbalPublicationFailureRecorder(connection: $connection, tables: $tables);
            $this->assertRecordingFails($recorder);
            $schema->install($connection);
            $schema->install($connection);
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM '.$parentSql));
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM '.$handlerSql));

            // Seed any overridden default counterpart without changing selected FKs.
            $defaultSchema = (new DbalPublicationFailureRecorderSchema())->schema();
            $schemaManager = $connection->createSchemaManager();
            if ('publication_failures' !== $failureTable) {
                $schemaManager->createTable($defaultSchema->getTable('publication_failures'));
            }
            if ('publication_handler_failures' !== $handlerTable) {
                $schemaManager->createTable($defaultSchema->getTable('publication_handler_failures'));
            }
            // The first record is an independent pipeline, then seed protected defaults.
            $recorder->record($this->failure(publicationName: 'legacy'));
            if ('publication_failures' !== $failureTable) {
                $connection->executeStatement('INSERT INTO publication_failures SELECT * FROM '.$parentSql);
            }
            if ('publication_handler_failures' !== $handlerTable) {
                $connection->executeStatement('INSERT INTO publication_handler_failures SELECT * FROM '.$handlerSql);
            }
            $defaultParents = $connection->fetchAllAssociative('SELECT * FROM publication_failures');
            $defaultHandlers = $connection->fetchAllAssociative('SELECT * FROM publication_handler_failures');

            $recorder->record($this->failure());
            $firstParents = $connection->fetchAllAssociative('SELECT * FROM '.$parentSql.' ORDER BY publication_name');
            $firstHandlers = $connection->fetchAllAssociative(
                'SELECT * FROM '.$handlerSql.' ORDER BY publication_name, handler_position',
            );
            self::assertCount(2, $firstParents);
            self::assertCount(4, $firstHandlers);
            self::assertSame('order-42', $firstParents[1]['aggregate_identifier']);
            self::assertSame('2026-08-09T09:15:30.123456+00:00', $firstParents[1]['dispatch_started_at']);
            self::assertSame('Inventory unavailable.', $firstHandlers[2]['diagnostic_message']);
            self::assertSame('Audit unavailable.', $firstHandlers[3]['diagnostic_message']);
            $recorder->record($this->failure(aggregateIdentifier: 'replacement-order'));
            $connection->close();
            $connection = $this->createConnection();
            if ($connection->getDatabasePlatform() instanceof SQLitePlatform) {
                $connection->executeStatement('PRAGMA foreign_keys = ON');
            }
            self::assertSame($firstParents, $connection->fetchAllAssociative(
                'SELECT * FROM '.$parentSql.' ORDER BY publication_name',
            ));
            self::assertSame($firstHandlers, $connection->fetchAllAssociative(
                'SELECT * FROM '.$handlerSql.' ORDER BY publication_name, handler_position',
            ));
            $recorder = new DbalPublicationFailureRecorder($connection, $tables);
            $recorder->record($this->failure(publicationName: 'orders.secondary'));
            $recorder->record($this->failure(globalPosition: 24));
            self::assertSame(4, $connection->fetchOne('SELECT COUNT(*) FROM '.$parentSql));
            self::assertSame(8, $connection->fetchOne('SELECT COUNT(*) FROM '.$handlerSql));
            if ('publication_failures' !== $failureTable) {
                self::assertSame($defaultParents, $connection->fetchAllAssociative('SELECT * FROM publication_failures'));
            }
            if ('publication_handler_failures' !== $handlerTable) {
                self::assertSame($defaultHandlers, $connection->fetchAllAssociative('SELECT * FROM publication_handler_failures'));
            }

            // The selected handler FK must target the selected parent, not its default.
            $connection->executeStatement('DELETE FROM '.$parentSql.' WHERE publication_name = ?', ['orders.secondary']);
            self::assertSame(0, $connection->fetchOne(
                'SELECT COUNT(*) FROM '.$handlerSql.' WHERE publication_name = ?', ['orders.secondary'],
            ));
            $connection->executeStatement('DROP TABLE '.$handlerSql);
            $this->assertRecordingFails($recorder, 25);
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM '.$parentSql.' WHERE global_position = 25'));
            $schema->install($connection);
            $recorder->record($this->failure(globalPosition: 25));
            self::assertSame(2, $connection->fetchOne('SELECT COUNT(*) FROM '.$handlerSql.' WHERE global_position = 25'));

            // Existing incompatible selected storage is not repaired or treated as default.
            $connection->executeStatement('DROP TABLE '.$handlerSql);
            $connection->executeStatement('CREATE TABLE '.$handlerSql.' (wrong_column INT)');
            $schema->install($connection);
            $this->assertRecordingFails($recorder, 26);
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM '.$parentSql.' WHERE global_position = 26'));
        } finally {
            $this->clearPublicationTables($connection);
        }
    }

    public function test_that_configured_runner_advances_only_after_atomic_recording_and_retries(): void
    {
        $connection = $this->createConnection();
        $this->clearPublicationTables($connection);
        $eventTables = new DbalEventStoreTables('runner_events', 'runner_position');
        $cursorTable = new DbalTableName('runner_cursor');
        foreach (['runner_events', 'runner_position', 'runner_cursor'] as $name) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$name);
        }
        $failureTables = new DbalPublicationFailureTables('Order', 'Group');
        $handlerSql = $failureTables->handlerFailureTable()->quoted($connection->getDatabasePlatform());
        try {
            new DbalEventStoreSchema($eventTables)->install($connection);
            new DbalPublicationCursorStoreSchema($cursorTable)->install($connection);
            $failureSchema = new DbalPublicationFailureRecorderSchema($failureTables);
            $failureSchema->install($connection);
            $eventMapper = new EventMapper([]);
            $eventMapper->register('orders', new EventMapping('failed', PublicationFailedEventFixture::class, 1));
            $eventStore = new DbalEventStore($connection, $eventMapper, tables: $eventTables);
            $eventStore->append(new StreamId('order', 'runner'), 0, [EventMessage::create(new PublicationFailedEventFixture())]);
            $attempts = 0;
            $eventDispatcher = new SimpleEventDispatcher();
            $eventDispatcher->addHandler(ClassName::underscore(PublicationFailedEventFixture::class),
                static function () use (&$attempts): never {
                    ++$attempts;
                    throw new RuntimeException('Subscriber failed.');
                },
            );
            $cursorStore = new DbalPublicationCursorStore($connection, $cursorTable);
            $publicationRunner = new EventPublicationRunner('configured', $eventStore, $eventDispatcher,
                $cursorStore, new DbalPublicationFailureRecorder($connection, $failureTables),
            );
            $connection->executeStatement('DROP TABLE '.$handlerSql);
            try {
                $publicationRunner->run(100);
                self::fail('Expected recorder storage failure to prevent advancement.');
            } catch (Exception) {
                self::assertSame(0, $cursorStore->load('configured'));
                self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM '.$failureTables->failureTable()->quoted($connection->getDatabasePlatform())));
            }
            $failureSchema->install($connection);
            $publicationRunner->run(100);
            self::assertSame(2, $attempts);
            self::assertSame(1, $cursorStore->load('configured'));
            self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM '.$handlerSql));
            $publicationRunner->run(100);
            self::assertSame(2, $attempts);
        } finally {
            $this->clearPublicationTables($connection);
            foreach (['runner_events', 'runner_position', 'runner_cursor'] as $name) {
                $connection->executeStatement('DROP TABLE IF EXISTS '.$name);
            }
        }
    }

    private function assertRecordingFails(DbalPublicationFailureRecorder $recorder, int $position = 23): void
    {
        try {
            $recorder->record($this->failure(globalPosition: $position));
            self::fail('Expected missing/incompatible selected storage to reject recording.');
        } catch (Exception) {
            self::assertTrue(true);
        }
    }

    private function clearPublicationTables(Connection $connection): void
    {
        if ($connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $connection->executeStatement('PRAGMA foreign_keys = ON');
        }
        foreach (['Group', 'publication_handler_failures', 'Order', 'publication_failures'] as $name) {
            $table = new DbalTableName($name);
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table->quoted($connection->getDatabasePlatform()));
        }
    }

    /**
     * Creates a database connection for the adapter under test
     */
    abstract protected function createConnection(): Connection;

    /**
     * Creates an installed DBAL publication failure recorder
     */
    protected function createPublicationFailureRecorder(): PublicationFailureRecorder
    {
        $this->conformanceConnection = $this->resetDatabase();

        return new DbalPublicationFailureRecorder($this->conformanceConnection);
    }

    /**
     * Returns DBAL-recorded correlation keys and their first aggregate evidence
     */
    protected function recordedFailureCorrelations(
        PublicationFailureRecorder $recorder,
    ): array {
        unset($recorder);

        self::assertNotNull($this->conformanceConnection);

        return array_map(
            static fn (array $row): array => [
                $row['publication_name'],
                $row['global_position'],
                $row['aggregate_identifier'],
            ],
            $this->conformanceConnection->fetchAllAssociative(
                <<<'SQL'
                    SELECT publication_name, global_position, aggregate_identifier
                    FROM publication_failures
                    ORDER BY publication_name, global_position
                    SQL,
            ),
        );
    }

    /**
     * Creates an empty installed publication-failure schema
     */
    protected function resetDatabase(): Connection
    {
        $connection = $this->createConnection();
        $connection->executeStatement('DROP TABLE IF EXISTS publication_handler_failures');
        $connection->executeStatement('DROP TABLE IF EXISTS publication_failures');

        $schema = new DbalPublicationFailureRecorderSchema();
        $schema->install($connection);

        return $connection;
    }
}
