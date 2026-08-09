<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Event\Sync;

use Fight\Common\Adapter\Messaging\Event\Sync\ServiceAwareEventDispatcher;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Application\Messaging\Event\EventHandlerFailure;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Domain\Messaging\Event\AllEvents;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Throwable;
use TypeError;

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

    public function test_that_dispatch_completes_service_handler_fan_out_and_reports_ordered_failures(): void
    {
        ServiceAwareDispatchCallLog::reset();
        $eventFailure = new RuntimeException('event service handler failed');
        $allEventsFailure = new TypeError('all-events service handler failed');
        $eventType = ClassName::underscore(SampleServiceEvent::class);
        $allEvents = ClassName::underscore(AllEvents::class);

        $this->container->shouldReceive('get')
            ->with('event_failure')
            ->andReturn(new FailingServiceAwareHandler('event-failure', $eventFailure));
        $this->container->shouldReceive('get')
            ->with('event_success')
            ->andReturn(new SuccessfulServiceAwareHandler('event-success'));
        $this->container->shouldReceive('get')
            ->with('all_events_failure')
            ->andReturn(new FailingServiceAwareHandler('all-events-failure', $allEventsFailure));
        $this->container->shouldReceive('get')
            ->with('all_events_success')
            ->andReturn(new SuccessfulServiceAwareHandler('all-events-success'));

        $this->dispatcher->addHandlerService($eventType, 'event_failure', 'handle', 20);
        $this->dispatcher->addHandlerService($eventType, 'event_success', 'handle', 10);
        $this->dispatcher->addHandlerService($allEvents, 'all_events_failure', 'handle', 20);
        $this->dispatcher->addHandlerService($allEvents, 'all_events_success', 'handle', 10);

        try {
            $this->dispatcher->dispatch(EventMessage::create(new SampleServiceEvent()));
            self::fail('Dispatch should report the collected service handler failures.');
        } catch (EventDispatchFailed $failed) {
            self::assertSame(
                ['event-failure', 'event-success', 'all-events-failure', 'all-events-success'],
                ServiceAwareDispatchCallLog::$calls,
            );
            self::assertSame(
                [
                    FailingServiceAwareHandler::class . '::handle',
                    FailingServiceAwareHandler::class . '::handle',
                ],
                array_map(
                    static fn (EventHandlerFailure $failure): string => $failure->callableDescription(),
                    $failed->failures(),
                ),
            );
            self::assertSame(
                [$eventFailure, $allEventsFailure],
                array_map(
                    static fn (EventHandlerFailure $failure): Throwable => $failure->throwable(),
                    $failed->failures(),
                ),
            );
        }
    }

    public function test_that_dispatch_propagates_container_resolution_failures_without_aggregation(): void
    {
        $resolutionFailure = new RuntimeException('service resolution failed');
        $eventType = ClassName::underscore(SampleServiceEvent::class);

        $this->container->shouldReceive('get')
            ->with('resolution_failure')
            ->andThrow($resolutionFailure);

        $dispatcher = new ServiceAwareEventDispatcher($this->container);
        $dispatcher->addHandlerService($eventType, 'resolution_failure', 'handle');

        try {
            $dispatcher->dispatch(EventMessage::create(new SampleServiceEvent()));
            self::fail('Dispatch should propagate the service resolution failure.');
        } catch (Throwable $throwable) {
            self::assertSame($resolutionFailure, $throwable);
            self::assertNotInstanceOf(EventDispatchFailed::class, $throwable);
        }
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

final class ServiceAwareDispatchCallLog
{
    /** @var string[] */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }
}

final readonly class FailingServiceAwareHandler
{
    public function __construct(
        private string $call,
        private Throwable $failure,
    ) {
    }

    public function handle(): void
    {
        ServiceAwareDispatchCallLog::$calls[] = $this->call;
        throw $this->failure;
    }
}

final readonly class SuccessfulServiceAwareHandler
{
    public function __construct(private string $call)
    {
    }

    public function handle(): void
    {
        ServiceAwareDispatchCallLog::$calls[] = $this->call;
    }
}
