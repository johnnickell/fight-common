<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Event\Sync;

use Fight\Common\Adapter\Messaging\Event\Sync\ServiceAwareEventDispatcher;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Container\ContainerInterface;

#[CoversClass(ServiceAwareEventDispatcher::class)]
class ServiceAwareEventDispatcherTest extends UnitTestCase
{
    /** @var MockInterface|ContainerInterface */
    private ContainerInterface $container;

    private ServiceAwareEventDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = $this->mock(ContainerInterface::class);
        $this->dispatcher = new ServiceAwareEventDispatcher($this->container);
    }

    public function test_that_register_service_with_simple_string_adds_handler_service(): void
    {
        $subscriber = new SampleServiceSubscriberSimple();

        $this->container->shouldReceive('get')
            ->with('my_service')
            ->andReturn($subscriber);

        $this->dispatcher->registerService(SampleServiceSubscriberSimple::class, 'my_service');
        $this->dispatcher->dispatch(EventMessage::create(new SampleServiceEvent()));

        self::assertTrue($subscriber->called);
    }

    public function test_that_register_service_with_priority_format_adds_handler_service(): void
    {
        $subscriber = new SampleServiceSubscriberPriority();

        $this->container->shouldReceive('get')
            ->with('my_service')
            ->andReturn($subscriber);

        $this->dispatcher->registerService(SampleServiceSubscriberPriority::class, 'my_service');
        $this->dispatcher->dispatch(EventMessage::create(new SampleServiceEvent()));

        self::assertTrue($subscriber->called);
    }

    public function test_that_register_service_with_array_of_handlers_adds_all(): void
    {
        $subscriber = new SampleServiceSubscriberMultiple();

        $this->container->shouldReceive('get')
            ->with('my_service')
            ->andReturn($subscriber);

        $this->dispatcher->registerService(SampleServiceSubscriberMultiple::class, 'my_service');
        $this->dispatcher->dispatch(EventMessage::create(new SampleServiceEvent()));

        self::assertContains('first', $subscriber->calls);
        self::assertContains('second', $subscriber->calls);
    }

    public function test_that_add_handler_service_stores_service_id(): void
    {
        $subscriber = new SampleServiceSubscriberSimple();

        $this->container->shouldReceive('get')
            ->with('svc')
            ->andReturn($subscriber);

        $eventType = ClassName::underscore(SampleServiceEvent::class);
        $this->dispatcher->addHandlerService($eventType, 'svc', 'onEvent');

        $handlers = $this->dispatcher->getHandlers($eventType);

        self::assertCount(1, $handlers);
    }

    public function test_that_dispatch_lazy_loads_service_for_event_type(): void
    {
        $subscriber = new SampleServiceSubscriberSimple();
        $eventType = ClassName::underscore(SampleServiceEvent::class);

        $this->container->shouldReceive('get')
            ->with('svc')
            ->andReturn($subscriber);

        $this->dispatcher->addHandlerService($eventType, 'svc', 'onEvent');
        $this->dispatcher->dispatch(EventMessage::create(new SampleServiceEvent()));

        self::assertTrue($subscriber->called);
    }

    public function test_that_get_handlers_with_null_lazy_loads_all_types(): void
    {
        $subscriber = new SampleServiceSubscriberSimple();
        $eventType = ClassName::underscore(SampleServiceEvent::class);

        $this->container->shouldReceive('get')
            ->with('svc')
            ->andReturn($subscriber);

        $this->dispatcher->addHandlerService($eventType, 'svc', 'onEvent');
        $handlers = $this->dispatcher->getHandlers();

        self::assertCount(1, $handlers);
    }

    public function test_that_get_handlers_with_type_lazy_loads_for_type(): void
    {
        $subscriber = new SampleServiceSubscriberSimple();
        $eventType = ClassName::underscore(SampleServiceEvent::class);

        $this->container->shouldReceive('get')
            ->with('svc')
            ->andReturn($subscriber);

        $this->dispatcher->addHandlerService($eventType, 'svc', 'onEvent');
        $handlers = $this->dispatcher->getHandlers($eventType);

        self::assertCount(1, $handlers);
    }

    public function test_that_has_handlers_with_null_returns_true_when_service_ids_present(): void
    {
        $eventType = ClassName::underscore(SampleServiceEvent::class);
        $this->dispatcher->addHandlerService($eventType, 'svc', 'onEvent');

        self::assertTrue($this->dispatcher->hasHandlers());
    }

    public function test_that_has_handlers_with_null_returns_false_when_nothing_registered(): void
    {
        self::assertFalse($this->dispatcher->hasHandlers());
    }

    public function test_that_has_handlers_with_type_returns_true_when_service_id_present(): void
    {
        $eventType = ClassName::underscore(SampleServiceEvent::class);
        $this->dispatcher->addHandlerService($eventType, 'svc', 'onEvent');

        self::assertTrue($this->dispatcher->hasHandlers($eventType));
    }

    public function test_that_has_handlers_with_type_delegates_to_parent_when_no_service(): void
    {
        self::assertFalse($this->dispatcher->hasHandlers('nonexistent_event'));
    }

    public function test_that_remove_handler_removes_from_service_ids_and_handlers(): void
    {
        $subscriber = new SampleServiceSubscriberSimple();
        $eventType = ClassName::underscore(SampleServiceEvent::class);

        $this->container->shouldReceive('get')
            ->with('svc')
            ->andReturn($subscriber);

        $this->dispatcher->addHandlerService($eventType, 'svc', 'onEvent');
        $this->dispatcher->getHandlers($eventType);

        $this->dispatcher->removeHandler($eventType, [$subscriber, 'onEvent']);

        self::assertFalse($this->dispatcher->hasHandlers($eventType));
    }

    public function test_that_lazy_load_reuses_same_service_instance(): void
    {
        $subscriber = new SampleServiceSubscriberSimple();
        $eventType = ClassName::underscore(SampleServiceEvent::class);

        $this->container->shouldReceive('get')
            ->with('svc')
            ->andReturn($subscriber);

        $this->dispatcher->addHandlerService($eventType, 'svc', 'onEvent');
        $this->dispatcher->getHandlers($eventType);

        $handlers = $this->dispatcher->getHandlers($eventType);

        self::assertCount(1, $handlers);
    }

    public function test_that_lazy_load_replaces_handler_when_service_changes(): void
    {
        $subscriber1 = new SampleServiceSubscriberSimple();
        $subscriber2 = new SampleServiceSubscriberSimple();
        $eventType = ClassName::underscore(SampleServiceEvent::class);

        $this->container->shouldReceive('get')
            ->with('svc')
            ->andReturn($subscriber1, $subscriber2);

        $this->dispatcher->addHandlerService($eventType, 'svc', 'onEvent');
        $this->dispatcher->getHandlers($eventType);

        $handlers = $this->dispatcher->getHandlers($eventType);

        self::assertCount(1, $handlers);
    }
}

class SampleServiceEvent implements Event
{
    public static function fromArray(array $data): static
    {
        return new static();
    }

    public function toArray(): array
    {
        return [];
    }
}

class SampleServiceSubscriberSimple implements EventSubscriber
{
    public bool $called = false;

    public static function eventRegistration(): array
    {
        return [SampleServiceEvent::class => 'onEvent'];
    }

    public function onEvent(EventMessage $m): void
    {
        $this->called = true;
    }
}

class SampleServiceSubscriberPriority implements EventSubscriber
{
    public bool $called = false;

    public static function eventRegistration(): array
    {
        return [SampleServiceEvent::class => ['onEvent', 5]];
    }

    public function onEvent(EventMessage $m): void
    {
        $this->called = true;
    }
}

class SampleServiceSubscriberMultiple implements EventSubscriber
{
    public array $calls = [];

    public static function eventRegistration(): array
    {
        return [SampleServiceEvent::class => [['onFirst', 10], ['onSecond']]];
    }

    public function onFirst(EventMessage $m): void
    {
        $this->calls[] = 'first';
    }

    public function onSecond(EventMessage $m): void
    {
        $this->calls[] = 'second';
    }
}
