<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\EventSourcing;

use DateTimeImmutable;
use Fight\Common\Domain\EventSourcing\EventMapper;
use Fight\Common\Domain\EventSourcing\EventMapping;
use Fight\Common\Domain\EventSourcing\EventMappingProvider;
use Fight\Common\Domain\EventSourcing\Exception\EventMappingException;
use Fight\Common\Domain\EventSourcing\MappedEvent;
use Fight\Common\Domain\EventSourcing\Upcaster;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EventMapper::class)]
#[CoversClass(EventMapping::class)]
#[CoversClass(MappedEvent::class)]
#[CoversClass(EventMappingException::class)]
class EventMapperTest extends UnitTestCase
{
    public function test_that_current_event_round_trips_by_stable_alias_independently_of_php_class(): void
    {
        $id = MessageId::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
        $timestamp = new DateTimeImmutable('2026-08-02T09:15:30.123456+00:00');
        $meta = Meta::create(['trace_id' => 'trace-7']);
        $message = new EventMessage($id, $timestamp, new OrderPlaced('order-42'), $meta);
        $mapper = new EventMapper([new OrdersEventMappingProvider(OrderPlaced::class)]);

        $mapped = $mapper->map($message);

        self::assertSame('orders.order-placed', $mapped->eventName());
        self::assertSame(1, $mapped->schemaVersion());
        self::assertSame(['order_id' => 'order-42'], $mapped->data());

        $hydrated = $mapper->hydrate(
            $mapped->eventName(),
            $mapped->schemaVersion(),
            $mapped->data(),
            $id,
            $timestamp,
            $meta,
        );

        self::assertInstanceOf(OrderPlaced::class, $hydrated->payload());
        self::assertSame($id, $hydrated->id());
        self::assertSame($timestamp, $hydrated->timestamp());
        self::assertSame(['trace_id' => 'trace-7'], $hydrated->meta()->toArray());
        self::assertSame(['order_id' => 'order-42'], $hydrated->payload()->toArray());

        $renamedMapper = new EventMapper([new OrdersEventMappingProvider(PurchaseConfirmed::class)]);
        $renamed = $renamedMapper->hydrate(
            $mapped->eventName(),
            $mapped->schemaVersion(),
            $mapped->data(),
            $id,
            $timestamp,
            $meta,
        );

        self::assertInstanceOf(PurchaseConfirmed::class, $renamed->payload());
        self::assertSame(['order_id' => 'order-42'], $renamed->payload()->toArray());
    }

    public function test_that_a_mapping_can_be_registered_directly_without_a_provider(): void
    {
        $id = MessageId::fromString('6ba7b813-9dad-11d1-80b4-00c04fd430c8');
        $timestamp = new DateTimeImmutable('2026-08-02T09:15:30.123456+00:00');
        $meta = Meta::create(['trace_id' => 'trace-8']);
        $mapper = new EventMapper([]);

        $mapper->register('orders', new EventMapping('order-placed', OrderPlaced::class, 1));

        $mapped = $mapper->map(new EventMessage($id, $timestamp, new OrderPlaced('order-43'), $meta));
        $hydrated = $mapper->hydrate(
            $mapped->eventName(),
            $mapped->schemaVersion(),
            $mapped->data(),
            $id,
            $timestamp,
            $meta,
        );

        self::assertSame('orders.order-placed', $mapped->eventName());
        self::assertInstanceOf(OrderPlaced::class, $hydrated->payload());
        self::assertSame(['order_id' => 'order-43'], $hydrated->payload()->toArray());
    }

    public function test_that_registration_rejects_duplicate_canonical_aliases(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Duplicate event alias: orders.order-placed.');

        new EventMapper([
            new ConfigurableEventMappingProvider('orders', [
                new EventMapping('order-placed', OrderPlaced::class, 1),
            ]),
            new ConfigurableEventMappingProvider('orders', [
                new EventMapping('order-placed', PurchaseConfirmed::class, 1),
            ]),
        ]);
    }

    public function test_that_registration_rejects_duplicate_current_event_classes(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Duplicate event class: Fight\Test\Common\Domain\EventSourcing\OrderPlaced.',
        );

        new EventMapper([
            new ConfigurableEventMappingProvider('orders', [
                new EventMapping('order-placed', OrderPlaced::class, 1),
                new EventMapping('order-confirmed', OrderPlaced::class, 1),
            ]),
        ]);
    }

    public function test_that_registration_accepts_explicit_durable_names_outside_a_lexical_convention(): void
    {
        $mapper = new EventMapper([
            new ConfigurableEventMappingProvider('Legacy_Orders', [
                new EventMapping('Order_Placed', OrderPlaced::class, 1),
            ]),
        ]);

        $mapped = $mapper->map(EventMessage::create(new OrderPlaced('order-42')));

        self::assertSame('Legacy_Orders.Order_Placed', $mapped->eventName());
    }

    public function test_that_registration_rejects_empty_durable_namespace_or_local_name(): void
    {
        $invalidProviders = [
            new ConfigurableEventMappingProvider('', [
                new EventMapping('order-placed', OrderPlaced::class, 1),
            ]),
            new ConfigurableEventMappingProvider('orders', [
                new EventMapping('', OrderPlaced::class, 1),
            ]),
        ];

        foreach ($invalidProviders as $invalidProvider) {
            try {
                new EventMapper([$invalidProvider]);
                self::fail('Empty durable event names must fail registration.');
            } catch (DomainException $exception) {
                self::assertSame('Event namespace and local name must be non-empty.', $exception->getMessage());
            }
        }
    }

    public function test_that_registration_rejects_schema_versions_below_one(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Event schema version must begin at one.');

        new EventMapper([
            new ConfigurableEventMappingProvider('orders', [
                new EventMapping('order-placed', OrderPlaced::class, 0),
            ]),
        ]);
    }

    public function test_that_registration_rejects_classes_that_are_not_events(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Mapped class must implement Event: Fight\Test\Common\Domain\EventSourcing\NonEventPayload.',
        );

        new EventMapper([
            new ConfigurableEventMappingProvider('orders', [
                new EventMapping('order-placed', NonEventPayload::class, 1),
            ]),
        ]);
    }

    public function test_that_version_one_registration_rejects_upcasters(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Schema version one mappings cannot declare upcasters.');

        new EventMapper([
            new ConfigurableEventMappingProvider('orders', [
                new EventMapping('order-placed', OrderPlaced::class, 1, [
                    new SchemaUpcaster(1, 2),
                ]),
            ]),
        ]);
    }

    public function test_that_registration_accepts_a_complete_sequential_upcaster_chain(): void
    {
        $mapper = new EventMapper([
            new ConfigurableEventMappingProvider('orders', [
                new EventMapping('order-placed', OrderPlaced::class, 3, [
                    new SchemaUpcaster(1, 2),
                    new SchemaUpcaster(2, 3),
                ]),
            ]),
        ]);

        self::assertInstanceOf(EventMapper::class, $mapper);
    }

    public function test_that_registration_rejects_incomplete_duplicate_or_skipping_upcaster_steps(): void
    {
        $invalidChains = [
            [new SchemaUpcaster(1, 2)],
            [new SchemaUpcaster(1, 2), new SchemaUpcaster(1, 2), new SchemaUpcaster(2, 3)],
            [new SchemaUpcaster(1, 3), new SchemaUpcaster(2, 3)],
        ];

        foreach ($invalidChains as $invalidChain) {
            try {
                new EventMapper([
                    new ConfigurableEventMappingProvider('orders', [
                        new EventMapping('order-placed', OrderPlaced::class, 3, $invalidChain),
                    ]),
                ]);
                self::fail('Invalid upcaster chains must fail registration.');
            } catch (DomainException $exception) {
                self::assertSame('Event mapping requires one sequential upcaster step per schema version.', $exception->getMessage());
            }
        }
    }

    public function test_that_hydration_sequentially_upcasts_historical_data_without_changing_stored_payload(): void
    {
        $storedData = ['legacy_order_id' => 'order-42'];
        $mapper = new EventMapper([
            new ConfigurableEventMappingProvider('orders', [
                new EventMapping('order-placed', EvolvedOrderPlaced::class, 3, [
                    new RenameOrderIdUpcaster(),
                    new AddOrderChannelUpcaster(),
                ]),
            ]),
        ]);

        $hydrated = $mapper->hydrate(
            'orders.order-placed',
            1,
            $storedData,
            MessageId::fromString('6ba7b811-9dad-11d1-80b4-00c04fd430c8'),
            new DateTimeImmutable('2026-08-02T09:15:30.123456+00:00'),
            Meta::create(),
        );

        self::assertInstanceOf(EvolvedOrderPlaced::class, $hydrated->payload());
        self::assertSame(
            ['order_id' => 'order-42', 'channel' => 'historical'],
            $hydrated->payload()->toArray(),
        );
        self::assertSame(['legacy_order_id' => 'order-42'], $storedData);
    }

    public function test_that_hydration_fails_closed_before_event_hydration_for_unmapped_or_unsupported_history(): void
    {
        $mapper = new EventMapper([
            new ConfigurableEventMappingProvider('orders', [
                new EventMapping('order-placed', HydrationTrackedEvent::class, 2, [
                    new SchemaUpcaster(1, 2),
                ]),
            ]),
        ]);
        $invalidStoredEvents = [
            ['orders.unknown', 1, 'Unknown event alias: orders.unknown.'],
            ['orders.order-placed', 3, 'Unsupported schema version 3 for event orders.order-placed.'],
            ['orders.order-placed', 0, 'Unsupported schema version 0 for event orders.order-placed.'],
        ];

        HydrationTrackedEvent::$hydrationCalls = 0;

        foreach ($invalidStoredEvents as [$eventName, $schemaVersion, $message]) {
            try {
                $mapper->hydrate(
                    $eventName,
                    $schemaVersion,
                    [],
                    MessageId::fromString('6ba7b812-9dad-11d1-80b4-00c04fd430c8'),
                    new DateTimeImmutable('2026-08-02T09:15:30.123456+00:00'),
                    Meta::create(),
                );
                self::fail('Unmapped or unsupported stored history must fail closed.');
            } catch (EventMappingException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        self::assertSame(0, HydrationTrackedEvent::$hydrationCalls);
    }

    public function test_that_mapping_an_unregistered_event_class_fails_closed(): void
    {
        $mapper = new EventMapper([new OrdersEventMappingProvider(OrderPlaced::class)]);

        $this->expectException(EventMappingException::class);
        $this->expectExceptionMessage(
            'Unknown event class: Fight\Test\Common\Domain\EventSourcing\PurchaseConfirmed.',
        );

        $mapper->map(EventMessage::create(new PurchaseConfirmed('order-42')));
    }
}

final readonly class ConfigurableEventMappingProvider implements EventMappingProvider
{
    /**
     * @param array<EventMapping> $mappings
     */
    public function __construct(private string $namespace, private array $mappings)
    {
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function mappings(): iterable
    {
        return $this->mappings;
    }
}

final readonly class SchemaUpcaster implements Upcaster
{
    public function __construct(private int $sourceSchemaVersion, private int $targetSchemaVersion)
    {
    }

    public function sourceSchemaVersion(): int
    {
        return $this->sourceSchemaVersion;
    }

    public function targetSchemaVersion(): int
    {
        return $this->targetSchemaVersion;
    }

    public function upcast(array $data): array
    {
        return $data;
    }
}

final readonly class RenameOrderIdUpcaster implements Upcaster
{
    public function sourceSchemaVersion(): int
    {
        return 1;
    }

    public function targetSchemaVersion(): int
    {
        return 2;
    }

    public function upcast(array $data): array
    {
        return ['order_id' => $data['legacy_order_id']];
    }
}

final readonly class AddOrderChannelUpcaster implements Upcaster
{
    public function sourceSchemaVersion(): int
    {
        return 2;
    }

    public function targetSchemaVersion(): int
    {
        return 3;
    }

    public function upcast(array $data): array
    {
        $data['channel'] = 'historical';

        return $data;
    }
}

final class NonEventPayload
{
}

final readonly class OrdersEventMappingProvider implements EventMappingProvider
{
    /**
     * @param class-string<Event> $eventClass
     */
    public function __construct(private string $eventClass)
    {
    }

    public function namespace(): string
    {
        return 'orders';
    }

    public function mappings(): iterable
    {
        yield new EventMapping('order-placed', $this->eventClass, 1);
    }
}

final readonly class OrderPlaced implements Event
{
    public function __construct(private string $orderId)
    {
    }

    public static function fromArray(array $data): static
    {
        return new self($data['order_id']);
    }

    public function toArray(): array
    {
        return ['order_id' => $this->orderId];
    }
}

final readonly class PurchaseConfirmed implements Event
{
    public function __construct(private string $orderId)
    {
    }

    public static function fromArray(array $data): static
    {
        return new self($data['order_id']);
    }

    public function toArray(): array
    {
        return ['order_id' => $this->orderId];
    }
}

final readonly class EvolvedOrderPlaced implements Event
{
    public function __construct(private string $orderId, private string $channel)
    {
    }

    public static function fromArray(array $data): static
    {
        return new self($data['order_id'], $data['channel']);
    }

    public function toArray(): array
    {
        return ['order_id' => $this->orderId, 'channel' => $this->channel];
    }
}

final class HydrationTrackedEvent implements Event
{
    public static int $hydrationCalls = 0;

    public static function fromArray(array $data): static
    {
        ++self::$hydrationCalls;

        return new self();
    }

    public function toArray(): array
    {
        return [];
    }
}
