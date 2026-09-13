<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalEventStore;
use Fight\Common\Domain\EventSourcing\EventMapper;
use Fight\Common\Domain\EventSourcing\EventMapping;
use Fight\Common\Domain\EventSourcing\EventMappingProvider;
use Fight\Common\Domain\EventSourcing\Exception\OptimisticConcurrencyException;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalEventStore::class)]
final class DbalEventStoreUnitTest extends UnitTestCase
{
    public function test_that_unsupported_platforms_are_rejected_at_construction(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->once()->andReturn(new OraclePlatform());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DbalEventStore supports SQLite, MySQL-compatible, and PostgreSQL only; received Doctrine\\DBAL\\Platforms\\OraclePlatform.');

        new DbalEventStore($connection, $this->eventMapper());
    }

    public function test_that_postgresql_is_an_accepted_platform(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->once()->andReturn(new PostgreSQLPlatform());

        self::assertInstanceOf(DbalEventStore::class, new DbalEventStore($connection, $this->eventMapper()));
    }

    public function test_that_append_persists_a_mapped_message_and_advances_the_global_position(): void
    {
        $connection = $this->sqliteConnection(2);
        $connection->shouldReceive('transactional')->once()->andReturnUsing(static function (callable $operation): void {
            $operation();
        });
        $connection->shouldReceive('executeStatement')->once();
        $connection->shouldReceive('fetchOne')->twice()->andReturn(0, 0);
        $connection->shouldReceive('fetchAssociative')->once()->andReturnFalse();
        $connection->shouldReceive('insert')->once()->withArgs(static function (string $table, array $row): bool {
            return $table === 'event_store_events'
                && $row['aggregate_name'] === 'order'
                && $row['aggregate_identifier'] === 'order-42'
                && $row['stream_version'] === 1
                && $row['global_position'] === 1
                && $row['event_name'] === 'orders.placed';
        });
        $connection->shouldReceive('update')->once()->with(
            'event_store_global_position',
            ['position' => 1],
            ['singleton' => 1],
        );

        (new DbalEventStore($connection, $this->eventMapper()))->append(
            new StreamId('order', 'order-42'),
            0,
            [$this->message('6ba7b841-9dad-11d1-80b4-00c04fd430c8', 'order-42')],
        );
    }

    public function test_that_append_rejects_duplicate_message_identifiers_before_persisting(): void
    {
        $connection = $this->sqliteConnection(2);
        $connection->shouldReceive('transactional')->once()->andReturnUsing(static function (callable $operation): void {
            $operation();
        });
        $connection->shouldReceive('executeStatement')->once();
        $connection->shouldReceive('fetchOne')->twice()->andReturn(0, 0);
        $connection->shouldReceive('fetchAssociative')->twice()->andReturnFalse();
        $connection->shouldNotReceive('insert');
        $store = new DbalEventStore($connection, $this->eventMapper());

        $this->expectException(OptimisticConcurrencyException::class);

        $store->append(new StreamId('order', 'order-42'), 0, [
            $this->message('6ba7b842-9dad-11d1-80b4-00c04fd430c8', 'one'),
            $this->message('6ba7b842-9dad-11d1-80b4-00c04fd430c8', 'two'),
        ]);
    }

    public function test_that_existing_messages_with_a_different_stream_identity_are_not_an_exact_retry(): void
    {
        $connection = $this->sqliteConnection(2);
        $connection->shouldReceive('transactional')->once()->andReturnUsing(static function (callable $operation): void {
            $operation();
        });
        $connection->shouldReceive('executeStatement')->once();
        $connection->shouldReceive('fetchOne')->twice()->andReturn(0, 0);
        $connection->shouldReceive('fetchAssociative')->twice()->andReturn(
            [
                'aggregate_name' => 'shipment',
                'aggregate_identifier' => 'order-42',
                'stream_version' => 1,
            ],
            [
                'aggregate_name' => 'order',
                'aggregate_identifier' => 'other-order',
                'stream_version' => 2,
            ],
        );
        $store = new DbalEventStore($connection, $this->eventMapper());

        $this->expectException(OptimisticConcurrencyException::class);

        $store->append(new StreamId('order', 'order-42'), 0, [
            $this->message('6ba7b849-9dad-11d1-80b4-00c04fd430c8', 'one'),
            $this->message('6ba7b850-9dad-11d1-80b4-00c04fd430c8', 'two'),
        ]);
    }

    public function test_that_an_existing_message_with_its_intended_stream_position_is_an_exact_retry(): void
    {
        $connection = $this->sqliteConnection(2);
        $connection->shouldReceive('transactional')->once()->andReturnUsing(static function (callable $operation): void {
            $operation();
        });
        $connection->shouldReceive('executeStatement')->once();
        $connection->shouldReceive('fetchOne')->twice()->andReturn(0, 1);
        $connection->shouldReceive('fetchAssociative')->once()->andReturn([
            'aggregate_name' => 'order',
            'aggregate_identifier' => 'order-42',
            'stream_version' => 1,
        ]);
        $connection->shouldNotReceive('insert');

        (new DbalEventStore($connection, $this->eventMapper()))->append(
            new StreamId('order', 'order-42'),
            0,
            [$this->message('6ba7b851-9dad-11d1-80b4-00c04fd430c8', 'order-42')],
        );
    }

    public function test_that_a_unique_constraint_race_is_an_exact_retry_when_every_message_matches(): void
    {
        $message = $this->message('6ba7b843-9dad-11d1-80b4-00c04fd430c8', 'order-42');
        $connection = $this->sqliteConnection();
        $connection->shouldReceive('transactional')->once()->andThrow($this->uniqueConstraintViolation());
        $connection->shouldReceive('fetchAssociative')->once()->andReturn([
            'aggregate_name' => 'order',
            'aggregate_identifier' => 'order-42',
            'stream_version' => 1,
        ]);

        (new DbalEventStore($connection, $this->eventMapper()))->append(
            new StreamId('order', 'order-42'),
            0,
            [$message],
        );
    }

    public function test_that_a_unique_constraint_race_with_a_newer_stream_becomes_an_optimistic_conflict(): void
    {
        $message = $this->message('6ba7b844-9dad-11d1-80b4-00c04fd430c8', 'order-42');
        $connection = $this->sqliteConnection();
        $connection->shouldReceive('transactional')->once()->andThrow($this->uniqueConstraintViolation());
        $connection->shouldReceive('fetchAssociative')->once()->andReturnFalse();
        $connection->shouldReceive('fetchOne')->once()->andReturn(1);
        $store = new DbalEventStore($connection, $this->eventMapper());

        $this->expectException(OptimisticConcurrencyException::class);

        $store->append(new StreamId('order', 'order-42'), 0, [$message]);
    }

    public function test_that_a_unique_constraint_race_with_an_existing_message_identifier_becomes_an_optimistic_conflict(): void
    {
        $message = $this->message('6ba7b845-9dad-11d1-80b4-00c04fd430c8', 'order-42');
        $connection = $this->sqliteConnection();
        $connection->shouldReceive('transactional')->once()->andThrow($this->uniqueConstraintViolation());
        $connection->shouldReceive('fetchAssociative')->once()->andReturnFalse();
        $connection->shouldReceive('fetchOne')->twice()->andReturn(0, (string) $message->id());
        $store = new DbalEventStore($connection, $this->eventMapper());

        $this->expectException(OptimisticConcurrencyException::class);

        $store->append(new StreamId('order', 'order-42'), 0, [$message]);
    }

    public function test_that_an_unrelated_unique_constraint_race_propagates_unchanged(): void
    {
        $failure = $this->uniqueConstraintViolation();
        $message = $this->message('6ba7b846-9dad-11d1-80b4-00c04fd430c8', 'order-42');
        $connection = $this->sqliteConnection();
        $connection->shouldReceive('transactional')->once()->andThrow($failure);
        $connection->shouldReceive('fetchAssociative')->once()->andReturnFalse();
        $connection->shouldReceive('fetchOne')->twice()->andReturn(0, false);
        $store = new DbalEventStore($connection, $this->eventMapper());

        $this->expectExceptionObject($failure);

        $store->append(new StreamId('order', 'order-42'), 0, [$message]);
    }

    public function test_that_stream_and_global_reads_hydrate_records_in_storage_order(): void
    {
        $connection = $this->sqliteConnection();
        $record = [
            'aggregate_name' => 'order',
            'aggregate_identifier' => 'order-42',
            'event_name' => 'orders.placed',
            'schema_version' => 1,
            'stream_version' => 1,
            'global_position' => 4,
            'payload' => json_encode(['order_id' => 'order-42'], JSON_THROW_ON_ERROR),
            'message_id' => '6ba7b847-9dad-11d1-80b4-00c04fd430c8',
            'message_timestamp' => '2026-08-02T10:15:00.000018+00:00',
            'message_meta' => json_encode(['trace' => 'test'], JSON_THROW_ON_ERROR),
        ];
        $connection->shouldReceive('fetchAllAssociative')->once()->andReturn([$record]);
        $connection->shouldReceive('fetchAllAssociative')->once()->with(
            'SELECT * FROM event_store_events WHERE global_position > ? ORDER BY global_position ASC LIMIT ?',
            [0, 10],
            [ParameterType::INTEGER, ParameterType::INTEGER],
        )->andReturn([$record]);
        $store = new DbalEventStore($connection, $this->eventMapper());

        $events = [...$store->readStream(new StreamId('order', 'order-42'))];

        self::assertSame(4, $events[0]->globalPosition());
        self::assertSame(['order_id' => 'order-42'], $events[0]->message()->payload()->toArray());
        self::assertSame(4, [...$store->readAllAfter(0, 10)][0]->globalPosition());
    }

    private function sqliteConnection(int $platformCalls = 1): Connection
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->times($platformCalls)->andReturn(new SQLitePlatform());

        return $connection;
    }

    private function eventMapper(): EventMapper
    {
        return new EventMapper([new class implements EventMappingProvider {
            public function namespace(): string
            {
                return 'orders';
            }

            public function mappings(): iterable
            {
                yield new EventMapping('placed', DbalEventStoreUnitEvent::class, 1);
            }
        }]);
    }

    private function message(string $messageId, string $orderId): EventMessage
    {
        return new EventMessage(
            MessageId::fromString($messageId),
            new DateTimeImmutable('2026-08-02T10:15:00.000018+00:00'),
            new DbalEventStoreUnitEvent($orderId),
            Meta::create(),
        );
    }

    private function uniqueConstraintViolation(): UniqueConstraintViolationException
    {
        $driverFailure = new class('forced unique constraint race') extends \RuntimeException implements DriverException {
            public function getSQLState(): ?string
            {
                return '23000';
            }
        };

        return new UniqueConstraintViolationException($driverFailure, null);
    }
}

final readonly class DbalEventStoreUnitEvent implements Event
{
    public function __construct(private string $orderId)
    {
    }

    public static function fromArray(array $data): static
    {
        return new self((string) $data['order_id']);
    }

    public function toArray(): array
    {
        return ['order_id' => $this->orderId];
    }
}
