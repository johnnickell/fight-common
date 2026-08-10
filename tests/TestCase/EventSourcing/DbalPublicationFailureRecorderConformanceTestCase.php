<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\EventSourcing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationFailureRecorder;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationFailureRecorderSchema;
use Fight\Common\Application\EventSourcing\PublicationFailureRecorder;
use LogicException;
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
