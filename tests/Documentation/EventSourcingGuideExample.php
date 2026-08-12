<?php

declare(strict_types=1);

namespace Fight\Test\Common\Documentation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\DependencyInjection\EventMappingProviderCompilerPass;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalEventStore;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalEventStoreSchema;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalProjectionCheckpointStore;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalProjectionCheckpointStoreSchema;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationCursorStore;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationCursorStoreSchema;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationFailureRecorder;
use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationFailureRecorderSchema;
use Fight\Common\Adapter\EventSourcing\Logging\LoggingPublicationFailureRecorder;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Application\EventSourcing\EventPublicationRunner;
use Fight\Common\Application\EventSourcing\ProjectionRunner;
use Fight\Common\Application\EventSourcing\Projector;
use Fight\Common\Domain\EventSourcing\AggregateDefinition;
use Fight\Common\Domain\EventSourcing\AggregateRoot;
use Fight\Common\Domain\EventSourcing\EventMapper;
use Fight\Common\Domain\EventSourcing\EventMapping;
use Fight\Common\Domain\EventSourcing\EventMappingProvider;
use Fight\Common\Domain\EventSourcing\EventSourcedRepository;
use Fight\Common\Domain\EventSourcing\StoredEvent;
use Fight\Common\Domain\Identity\UniqueId;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Utility\ClassName;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Executable form of the public journey documented in docs/event-sourcing.md
 */
final readonly class EventSourcingGuideExample
{
    /**
     * Runs the guide's aggregate, projection, and publication journeys
     *
     * @return array{
     *     aggregate_name: string,
     *     aggregate_version: int,
     *     pending_events: array<Event>,
     *     first_order_id: string,
     *     second_order_id: string,
     *     projection: array<string, array{name: string, global_position: int}>,
     *     projection_checkpoint: int,
     *     dispatched_names: list<string>,
     *     publication_cursor: int,
     *     publication_failures: int
     * }
     */
    public static function run(): array
    {
        [$connection, $eventStore, $repository] = self::createPersistenceServices();
        [$firstOrderId, $reloaded, $secondOrderId] = self::persistOrders($repository);
        [$projection, $projectionCheckpoint] = self::projectOrders(
            $connection,
            $eventStore,
            $firstOrderId,
            $secondOrderId,
        );
        [$dispatchedNames, $publicationCursor, $publicationFailures] = self::publishEvents(
            $connection,
            $eventStore,
        );
        self::verifySymfonyExamples();

        return [
            'aggregate_name' => $reloaded->name(),
            'aggregate_version' => $reloaded->version(),
            'pending_events' => $reloaded->releaseEvents(),
            'first_order_id' => $firstOrderId->toString(),
            'second_order_id' => $secondOrderId->toString(),
            'projection' => $projection,
            'projection_checkpoint' => $projectionCheckpoint,
            'dispatched_names' => $dispatchedNames,
            'publication_cursor' => $publicationCursor,
            'publication_failures' => $publicationFailures,
        ];
    }

    /** @return array{Connection, DbalEventStore, EventSourcedRepository} */
    private static function createPersistenceServices(): array
    {
        // --8<-- [start:event-store-composition]
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new DbalEventStoreSchema())->install($connection);

        // --8<-- [start:manual-mapper]
        $eventMapper = new EventMapper([new OrdersEventMappingProvider()]);
        // --8<-- [end:manual-mapper]
        $eventStore = new DbalEventStore($connection, $eventMapper);
        $repository = new EventSourcedRepository(
            $eventStore,
            new AggregateDefinition('orders', Order::class),
        );
        // --8<-- [end:event-store-composition]

        return [$connection, $eventStore, $repository];
    }

    /**
     * @param EventSourcedRepository $repository
     *
     * @return array{OrderId, Order, OrderId}
     */
    private static function persistOrders(EventSourcedRepository $repository): array
    {
        // --8<-- [start:persist-reload]
        $firstOrderId = OrderId::generate();
        $firstOrder = Order::place($firstOrderId, 'Original name');
        $firstOrder->rename('Current name');
        $repository->save($firstOrder);

        $reloaded = $repository->find($firstOrderId);

        if (!$reloaded instanceof Order) {
            throw new RuntimeException('The documented order must reload from its event stream.');
        }

        $secondOrderId = OrderId::generate();
        $repository->save(Order::place($secondOrderId, 'Second order'));
        // --8<-- [end:persist-reload]

        return [$firstOrderId, $reloaded, $secondOrderId];
    }

    /**
     * @return array{array<string, array{name: string, global_position: int}>, int}
     */
    private static function projectOrders(
        Connection $connection,
        DbalEventStore $eventStore,
        OrderId $firstOrderId,
        OrderId $secondOrderId,
    ): array {
        // --8<-- [start:projection-composition]
        (new DbalProjectionCheckpointStoreSchema())->install($connection);
        $orderSummaryWriter = new InMemoryOrderSummaryWriter();
        $orderSummaryProjector = new OrderSummaryProjector($orderSummaryWriter);
        $checkpointStore = new DbalProjectionCheckpointStore($connection);
        $projectionRunner = new ProjectionRunner($eventStore, $checkpointStore);
        $projectionRunner->run($orderSummaryProjector, 100);
        // --8<-- [end:projection-composition]

        self::runProjectionWorker(
            static fn(): bool => true,
            $checkpointStore,
            $projectionRunner,
            $orderSummaryProjector,
        );

        $orderSummaryWriter->upsertIfNewer($firstOrderId->toString(), 'Stale first order', 1);
        $orderSummaryWriter->upsertIfNewer($secondOrderId->toString(), 'Stale second order', 2);

        $checkpoint = $checkpointStore->load($orderSummaryProjector->name());
        self::resetProjection($checkpointStore);

        return [$orderSummaryWriter->state(), $checkpoint];
    }

    /** @return array{list<string>, int, int} */
    private static function publishEvents(Connection $connection, DbalEventStore $eventStore): array
    {
        // --8<-- [start:publication-composition]
        (new DbalPublicationCursorStoreSchema())->install($connection);
        (new DbalPublicationFailureRecorderSchema())->install($connection);
        $dispatchedNames = [];
        $eventDispatcher = new SimpleEventDispatcher();
        $recordName = static function (EventMessage $message) use (&$dispatchedNames): void {
            $payload = $message->payload();

            if ($payload instanceof OrderPlaced || $payload instanceof OrderRenamed) {
                $dispatchedNames[] = $payload->name;
            }
        };
        $eventDispatcher->addHandler(ClassName::underscore(OrderPlaced::class), $recordName);
        $eventDispatcher->addHandler(ClassName::underscore(OrderRenamed::class), $recordName);
        $eventDispatcher->addHandler(
            ClassName::underscore(OrderRenamed::class),
            static fn(): never => throw new RuntimeException('Documented subscriber failure.'),
        );

        $cursorStore = new DbalPublicationCursorStore($connection);
        $durableFailureRecorder = new DbalPublicationFailureRecorder($connection);
        $failureRecorder = new LoggingPublicationFailureRecorder($durableFailureRecorder, new NullLogger());
        $publicationRunner = new EventPublicationRunner(
            'orders.subscribers',
            $eventStore,
            $eventDispatcher,
            $cursorStore,
            $failureRecorder,
        );
        // --8<-- [end:publication-composition]
        // --8<-- [start:publication-run]
        $publicationRunner->run(100);
        // --8<-- [end:publication-run]

        return [
            $dispatchedNames,
            $cursorStore->load('orders.subscribers'),
            (int) $connection->fetchOne('SELECT COUNT(*) FROM publication_failures'),
        ];
    }

    private static function configureSymfonyContainer(ContainerBuilder $container): void
    {
        // --8<-- [start:symfony-container]
        $container->registerForAutoconfiguration(EventMappingProvider::class)
            ->addTag('common.event_mapping_provider');

        $container->register(EventMapper::class, EventMapper::class)
            ->setArguments([[]]);

        $container->register(OrdersEventMappingProvider::class, OrdersEventMappingProvider::class)
            ->setAutoconfigured(true)
            ->setAutowired(true);

        $container->addCompilerPass(new EventMappingProviderCompilerPass());
        // --8<-- [end:symfony-container]
    }

    private static function verifySymfonyExamples(): void
    {
        $container = new ContainerBuilder();
        self::configureSymfonyContainer($container);
        $container->getDefinition(EventMapper::class)->setPublic(true);
        $container->compile();

        if (!$container->get(EventMapper::class) instanceof EventMapper) {
            throw new RuntimeException('The documented mapper must resolve from the compiled container.');
        }

        self::wireMappingProvider(
            new Definition(EventMapper::class, [[]]),
            OrdersEventMappingProvider::class,
        );
    }

    private static function wireMappingProvider(
        Definition $mapperDefinition,
        string $providerServiceId,
    ): void {
        // --8<-- [start:symfony-provider-reference]
        $mapperDefinition->addMethodCall('registerProvider', [new Reference($providerServiceId)]);
        // --8<-- [end:symfony-provider-reference]
    }

    private static function runProjectionWorker(
        callable $shutdownRequested,
        DbalProjectionCheckpointStore $checkpointStore,
        ProjectionRunner $projectionRunner,
        OrderSummaryProjector $orderSummaryProjector,
    ): void {
        // --8<-- [start:projection-worker]
        while (!$shutdownRequested()) {
            $before = $checkpointStore->load($orderSummaryProjector->name());

            $projectionRunner->run($orderSummaryProjector, 100);

            if ($before === $checkpointStore->load($orderSummaryProjector->name())) {
                usleep(250_000);
            }
        }
        // --8<-- [end:projection-worker]
    }

    private static function resetProjection(DbalProjectionCheckpointStore $checkpointStore): void
    {
        // --8<-- [start:projection-reset]
        $checkpointStore->reset('orders.order-summary');
        // --8<-- [end:projection-reset]
    }
}

// --8<-- [start:aggregate-types]
final readonly class OrderId extends UniqueId
{
}

final readonly class OrderPlaced implements Event
{
    public function __construct(
        public OrderId $orderId,
        public string $name,
    ) {
    }

    public static function fromArray(array $data): static
    {
        return new self(OrderId::fromString($data['order_id']), $data['name']);
    }

    public function toArray(): array
    {
        return ['order_id' => $this->orderId->toString(), 'name' => $this->name];
    }
}

final readonly class OrderRenamed implements Event
{
    public function __construct(public string $name)
    {
    }

    public static function fromArray(array $data): static
    {
        return new self($data['name']);
    }

    public function toArray(): array
    {
        return ['name' => $this->name];
    }
}

final class Order extends AggregateRoot
{
    private string $name = '';

    private function __construct(OrderId $id)
    {
        parent::__construct($id);
    }

    public static function place(OrderId $id, string $name): self
    {
        $order = new self($id);
        $order->record(new OrderPlaced($id, $name));

        return $order;
    }

    public static function reconstitute(iterable $events): static
    {
        $order = null;

        foreach ($events as $event) {
            if (null === $order) {
                if (!$event instanceof OrderPlaced) {
                    throw new RuntimeException('Order history must begin with OrderPlaced.');
                }

                $order = new static($event->orderId);
            }

            $order->replay($event);
        }

        return $order ?? throw new RuntimeException('Order history cannot be empty.');
    }

    public function rename(string $name): void
    {
        $this->record(new OrderRenamed($name));
    }

    public function name(): string
    {
        return $this->name;
    }

    protected function apply(Event $event): void
    {
        match (true) {
            $event instanceof OrderPlaced => $this->whenOrderPlaced($event),
            $event instanceof OrderRenamed => $this->whenOrderRenamed($event),
            default => throw new RuntimeException(sprintf('Unsupported order event: %s.', $event::class)),
        };
    }

    private function whenOrderPlaced(OrderPlaced $event): void
    {
        $this->name = $event->name;
    }

    private function whenOrderRenamed(OrderRenamed $event): void
    {
        $this->name = $event->name;
    }
}

final readonly class OrdersEventMappingProvider implements EventMappingProvider
{
    public function namespace(): string
    {
        return 'orders';
    }

    public function mappings(): iterable
    {
        yield new EventMapping('placed', OrderPlaced::class, 1);
        yield new EventMapping('renamed', OrderRenamed::class, 1);
    }
}
// --8<-- [end:aggregate-types]

// --8<-- [start:projection-types]
interface OrderSummaryWriter
{
    public function upsertIfNewer(string $orderId, string $name, int $globalPosition): void;
}

final class InMemoryOrderSummaryWriter implements OrderSummaryWriter
{
    /** @var array<string, array{name: string, global_position: int}> */
    private array $state = [];

    public function upsertIfNewer(string $orderId, string $name, int $globalPosition): void
    {
        $summary = $this->state[$orderId] ?? null;

        if (null === $summary || $globalPosition > $summary['global_position']) {
            $this->state[$orderId] = ['name' => $name, 'global_position' => $globalPosition];
        }
    }

    /** @return array<string, array{name: string, global_position: int}> */
    public function state(): array
    {
        return $this->state;
    }
}

final readonly class OrderSummaryProjector implements Projector
{
    public function __construct(private OrderSummaryWriter $writer)
    {
    }

    public function name(): string
    {
        return 'orders.order-summary';
    }

    public function eventClasses(): iterable
    {
        yield OrderPlaced::class;
        yield OrderRenamed::class;
    }

    public function project(StoredEvent $event): void
    {
        $payload = $event->message()->payload();
        $name = match (true) {
            $payload instanceof OrderPlaced => $payload->name,
            $payload instanceof OrderRenamed => $payload->name,
            default => throw new RuntimeException('The runner delivered an undeclared event.'),
        };

        $this->writer->upsertIfNewer(
            $event->streamId()->identifier(),
            $name,
            $event->globalPosition(),
        );
    }
}
// --8<-- [end:projection-types]
