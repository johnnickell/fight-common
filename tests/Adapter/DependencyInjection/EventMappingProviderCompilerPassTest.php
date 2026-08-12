<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\DependencyInjection;

use Fight\Common\Adapter\DependencyInjection\EventMappingProviderCompilerPass;
use Fight\Common\Domain\EventSourcing\EventMapper;
use Fight\Common\Domain\EventSourcing\EventMapping;
use Fight\Common\Domain\EventSourcing\EventMappingProvider;
use Fight\Common\Domain\EventSourcing\Exception\EventMappingException;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(EventMappingProviderCompilerPass::class)]
class EventMappingProviderCompilerPassTest extends UnitTestCase
{
    public function test_that_autoconfigured_mapping_providers_are_composed_into_the_event_mapper(): void
    {
        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(EventMappingProvider::class)
            ->addTag('common.event_mapping_provider');
        $container->register(EventMapper::class, EventMapper::class)
            ->setArguments([[]])
            ->setPublic(true);
        $container->register(MappingNamespace::class, MappingNamespace::class);
        $container->register(OrdersEventMappingProvider::class, OrdersEventMappingProvider::class)
            ->setAutoconfigured(true)
            ->setAutowired(true);
        $container->addCompilerPass(new EventMappingProviderCompilerPass());

        $container->compile();

        $mapper = $container->get(EventMapper::class);
        self::assertInstanceOf(EventMapper::class, $mapper);

        $mapped = $mapper->map(EventMessage::create(new AutoconfiguredOrderPlaced('order-42')));

        self::assertSame('orders.order-placed', $mapped->eventName());
        self::assertSame(1, $mapped->schemaVersion());
        self::assertSame(['order_id' => 'order-42'], $mapped->data());
    }

    public function test_that_multiple_autoconfigured_mapping_providers_are_composed_deterministically(): void
    {
        $container = $this->containerWithMapper();
        $container->register(MappingNamespace::class, MappingNamespace::class);
        $container->register(OrdersEventMappingProvider::class, OrdersEventMappingProvider::class)
            ->setAutoconfigured(true)
            ->setAutowired(true);
        $container->register(BillingEventMappingProvider::class, BillingEventMappingProvider::class)
            ->setAutoconfigured(true);

        $container->compile();

        $mapper = $container->get(EventMapper::class);
        self::assertInstanceOf(EventMapper::class, $mapper);

        $order = $mapper->map(EventMessage::create(new AutoconfiguredOrderPlaced('order-43')));
        $payment = $mapper->map(EventMessage::create(new AutoconfiguredPaymentCaptured('payment-7')));

        self::assertSame('orders.order-placed', $order->eventName());
        self::assertSame('billing.payment-captured', $payment->eventName());
    }

    public function test_that_duplicate_provider_contributions_fail_when_the_compiled_mapper_is_resolved(): void
    {
        $container = $this->containerWithMapper();
        $container->register(MappingNamespace::class, MappingNamespace::class);
        $container->register(OrdersEventMappingProvider::class, OrdersEventMappingProvider::class)
            ->setAutoconfigured(true)
            ->setAutowired(true);
        $container->register(DuplicateOrdersEventMappingProvider::class, DuplicateOrdersEventMappingProvider::class)
            ->setAutoconfigured(true);
        $container->compile();

        $this->expectException(EventMappingException::class);
        $this->expectExceptionMessage('Duplicate event alias: orders.order-placed.');

        $container->get(EventMapper::class);
    }

    public function test_that_invalid_provider_contributions_fail_when_the_compiled_mapper_is_resolved(): void
    {
        $container = $this->containerWithMapper();
        $container->register(InvalidEventMappingProvider::class, InvalidEventMappingProvider::class)
            ->setAutoconfigured(true);
        $container->compile();

        $this->expectException(EventMappingException::class);
        $this->expectExceptionMessage('Event namespace and local name must be non-empty.');

        $container->get(EventMapper::class);
    }

    public function test_that_compilation_succeeds_when_no_event_mapper_is_configured(): void
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new EventMappingProviderCompilerPass());

        $container->compile();

        self::assertFalse($container->has(EventMapper::class));
    }

    private function containerWithMapper(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(EventMappingProvider::class)
            ->addTag('common.event_mapping_provider');
        $container->register(EventMapper::class, EventMapper::class)
            ->setArguments([[]])
            ->setPublic(true);
        $container->addCompilerPass(new EventMappingProviderCompilerPass());

        return $container;
    }
}

final readonly class OrdersEventMappingProvider implements EventMappingProvider
{
    public function __construct(private MappingNamespace $mappingNamespace) {}

    public function namespace(): string
    {
        return $this->mappingNamespace->value;
    }

    public function mappings(): iterable
    {
        return [new EventMapping('order-placed', AutoconfiguredOrderPlaced::class, 1)];
    }
}

final readonly class BillingEventMappingProvider implements EventMappingProvider
{
    public function namespace(): string
    {
        return 'billing';
    }

    public function mappings(): iterable
    {
        return [new EventMapping('payment-captured', AutoconfiguredPaymentCaptured::class, 1)];
    }
}

final readonly class DuplicateOrdersEventMappingProvider implements EventMappingProvider
{
    public function namespace(): string
    {
        return 'orders';
    }

    public function mappings(): iterable
    {
        return [new EventMapping('order-placed', AutoconfiguredPaymentCaptured::class, 1)];
    }
}

final readonly class InvalidEventMappingProvider implements EventMappingProvider
{
    public function namespace(): string
    {
        return '';
    }

    public function mappings(): iterable
    {
        return [];
    }
}

final readonly class MappingNamespace
{
    public function __construct(public string $value = 'orders') {}
}

final readonly class AutoconfiguredOrderPlaced implements Event
{
    public function __construct(private string $orderId) {}

    public function toArray(): array
    {
        return ['order_id' => $this->orderId];
    }

    public static function fromArray(array $data): static
    {
        return new self($data['order_id']);
    }
}

final readonly class AutoconfiguredPaymentCaptured implements Event
{
    public function __construct(private string $paymentId) {}

    public function toArray(): array
    {
        return ['payment_id' => $this->paymentId];
    }

    public static function fromArray(array $data): static
    {
        return new self($data['payment_id']);
    }
}
