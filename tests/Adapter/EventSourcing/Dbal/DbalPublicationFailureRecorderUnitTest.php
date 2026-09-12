<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationFailureRecorder;
use Fight\Common\Application\EventSourcing\EventPublicationFailure;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Application\Messaging\Event\EventHandlerFailure;
use Fight\Common\Domain\EventSourcing\StoredEvent;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalPublicationFailureRecorder::class)]
final class DbalPublicationFailureRecorderUnitTest extends UnitTestCase
{
    public function test_that_record_inserts_the_failure_and_each_handler_snapshot_atomically(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('transactional')->once()->andReturnUsing(
            function (callable $operation) use ($connection): void {
                $operation($connection);
            },
        );
        $connection->shouldReceive('insert')->once()->with('publication_failures', [
            'publication_name' => 'orders.subscribers',
            'aggregate_name' => 'order',
            'aggregate_identifier' => 'order-42',
            'event_name' => 'orders.order-placed',
            'schema_version' => 2,
            'stream_version' => 7,
            'global_position' => 23,
            'message_id' => '6ba7b841-9dad-11d1-80b4-00c04fd430c8',
            'dispatch_started_at' => '2026-08-09T09:15:30.123456+00:00',
        ]);
        $connection->shouldReceive('insert')->once()->with('publication_handler_failures', [
            'publication_name' => 'orders.subscribers',
            'global_position' => 23,
            'handler_position' => 0,
            'callable_description' => 'OrdersSubscriber::onOrderPlaced',
            'exception_class' => \RuntimeException::class,
            'exception_code' => 73,
            'diagnostic_message' => 'Inventory unavailable.',
        ]);

        (new DbalPublicationFailureRecorder($connection))->record($this->failure());
    }

    public function test_that_record_keeps_the_first_evidence_when_the_correlation_key_already_exists(): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('transactional')->once()->andThrow($this->uniqueConstraintViolation());

        (new DbalPublicationFailureRecorder($connection))->record($this->failure());

        self::assertTrue(true);
    }

    private function failure(): EventPublicationFailure
    {
        $event = new class implements Event {
            public static function fromArray(array $data): static
            {
                return new self();
            }

            public function toArray(): array
            {
                return [];
            }
        };
        $storedEvent = new StoredEvent(
            new StreamId('order', 'order-42'),
            'orders.order-placed',
            2,
            7,
            23,
            new EventMessage(
                MessageId::fromString('6ba7b841-9dad-11d1-80b4-00c04fd430c8'),
                new DateTimeImmutable('2026-08-09T09:14:00.000001+00:00'),
                $event,
                Meta::create(),
            ),
        );

        return EventPublicationFailure::fromDispatchFailure(
            'orders.subscribers',
            $storedEvent,
            new DateTimeImmutable('2026-08-09T04:15:30.123456-05:00'),
            new EventDispatchFailed([
                new EventHandlerFailure(
                    'OrdersSubscriber::onOrderPlaced',
                    new \RuntimeException('Inventory unavailable.', 73),
                ),
            ]),
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
