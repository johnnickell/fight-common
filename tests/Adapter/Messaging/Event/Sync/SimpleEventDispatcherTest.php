<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Event\Sync;

use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Application\Messaging\Event\EventHandlerFailure;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Domain\Messaging\Event\AllEvents;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use TypeError;

#[CoversClass(SimpleEventDispatcher::class)]
class SimpleEventDispatcherTest extends UnitTestCase
{
    private SimpleEventDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new SimpleEventDispatcher();
    }

    public function test_that_trigger_wraps_event_and_dispatches_to_handlers(): void
    {
        $received = [];
        $key = ClassName::underscore(SampleDispatchEvent::class);
        $this->dispatcher->addHandler(
            $key,
            function (EventMessage $m) use (&$received): void {
                $received[] = $m->payload();
            }
        );

        $event = new SampleDispatchEvent();
        $this->dispatcher->trigger($event);

        self::assertCount(1, $received);
        self::assertSame($event, $received[0]);
    }

    public function test_that_dispatch_calls_handlers_for_event_type_and_all_events(): void
    {
        $calls = [];
        $this->dispatcher->addHandler(
            ClassName::underscore(SampleDispatchEvent::class),
            function () use (&$calls): void { $calls[] = 'event'; }
        );
        $this->dispatcher->addHandler(
            ClassName::underscore(AllEvents::class),
            function () use (&$calls): void { $calls[] = 'all'; }
        );

        $this->dispatcher->dispatch(EventMessage::create(new SampleDispatchEvent()));

        self::assertSame(['event', 'all'], $calls);
    }

    public function test_that_dispatch_collects_every_handler_failure_after_both_priority_phases(): void
    {
        FailureDispatchCallLog::reset();
        $objectFailure = new RuntimeException('object handler failed');
        $functionFailure = new \Error('function handler failed');
        $staticFailure = new RuntimeException('static handler failed');
        $invokableFailure = new RuntimeException('invokable handler failed');
        $closureFailure = new TypeError('closure handler failed');
        $objectHandler = new FailureDispatchHandler($objectFailure);
        $invokableHandler = new InvokableFailureDispatchHandler($invokableFailure);
        FailureDispatchCallLog::$functionFailure = $functionFailure;
        StaticFailureDispatchHandler::$failure = $staticFailure;
        $eventType = ClassName::underscore(SampleDispatchEvent::class);
        $allEvents = ClassName::underscore(AllEvents::class);

        $this->dispatcher->addHandler($eventType, [$objectHandler, 'onEvent'], 30);
        $this->dispatcher->addHandler($eventType, __NAMESPACE__ . '\\sample_failing_dispatch_handler', 20);
        $this->dispatcher->addHandler($eventType, [StaticFailureDispatchHandler::class, 'onEvent'], 17);
        $this->dispatcher->addHandler($eventType, $invokableHandler, 15);
        $this->dispatcher->addHandler(
            $eventType,
            static function (): void {
                FailureDispatchCallLog::record('event-success');
            },
            10,
        );
        $this->dispatcher->addHandler(
            $allEvents,
            static function () use ($closureFailure): void {
                FailureDispatchCallLog::record('closure');
                throw $closureFailure;
            },
            20,
        );
        $this->dispatcher->addHandler(
            $allEvents,
            static function (): void {
                FailureDispatchCallLog::record('all-success');
            },
            10,
        );

        try {
            $this->dispatcher->dispatch(EventMessage::create(new SampleDispatchEvent()));
            self::fail('Dispatch should report the collected handler failures.');
        } catch (EventDispatchFailed $failed) {
            self::assertSame(
                ['object', 'function', 'static', 'invokable', 'event-success', 'closure', 'all-success'],
                FailureDispatchCallLog::$calls,
            );
            self::assertSame(
                [
                    FailureDispatchHandler::class . '::onEvent',
                    __NAMESPACE__ . '\\sample_failing_dispatch_handler',
                    StaticFailureDispatchHandler::class . '::onEvent',
                    InvokableFailureDispatchHandler::class . '::__invoke',
                    'Closure (non-replayable)',
                ],
                array_map(
                    static fn (EventHandlerFailure $failure): string => $failure->callableDescription(),
                    $failed->failures(),
                ),
            );
            self::assertSame(
                [$objectFailure, $functionFailure, $staticFailure, $invokableFailure, $closureFailure],
                array_map(
                    static fn (EventHandlerFailure $failure): \Throwable => $failure->throwable(),
                    $failed->failures(),
                ),
            );
        }
    }

    public function test_that_dispatch_with_no_handlers_does_nothing(): void
    {
        $this->dispatcher->dispatch(EventMessage::create(new SampleDispatchEvent()));
        self::assertTrue(true);
    }

    public function test_that_register_with_simple_string_format_adds_handler(): void
    {
        $subscriber = new class implements EventSubscriber {
            public bool $called = false;

            public static function eventRegistration(): array
            {
                return [SampleDispatchEvent::class => 'onEvent'];
            }

            public function onEvent(EventMessage $m): void
            {
                $this->called = true;
            }
        };

        $this->dispatcher->register($subscriber);
        $this->dispatcher->dispatch(EventMessage::create(new SampleDispatchEvent()));

        self::assertTrue($subscriber->called);
    }

    public function test_that_register_with_method_and_priority_format_adds_handler(): void
    {
        $subscriber = new class implements EventSubscriber {
            public bool $called = false;

            public static function eventRegistration(): array
            {
                return [SampleDispatchEvent::class => ['onEvent', 10]];
            }

            public function onEvent(EventMessage $m): void
            {
                $this->called = true;
            }
        };

        $this->dispatcher->register($subscriber);
        $this->dispatcher->dispatch(EventMessage::create(new SampleDispatchEvent()));

        self::assertTrue($subscriber->called);
    }

    public function test_that_register_with_array_of_handlers_format_adds_all_handlers(): void
    {
        $subscriber = new class implements EventSubscriber {
            public array $calls = [];

            public static function eventRegistration(): array
            {
                return [SampleDispatchEvent::class => [['onFirst', 10], ['onSecond']]];
            }

            public function onFirst(EventMessage $m): void
            {
                $this->calls[] = 'first';
            }

            public function onSecond(EventMessage $m): void
            {
                $this->calls[] = 'second';
            }
        };

        $this->dispatcher->register($subscriber);
        $this->dispatcher->dispatch(EventMessage::create(new SampleDispatchEvent()));

        self::assertContains('first', $subscriber->calls);
        self::assertContains('second', $subscriber->calls);
    }

    public function test_that_unregister_removes_simple_string_handler(): void
    {
        $subscriber = new class implements EventSubscriber {
            public bool $called = false;

            public static function eventRegistration(): array
            {
                return [SampleDispatchEvent::class => 'onEvent'];
            }

            public function onEvent(EventMessage $m): void
            {
                $this->called = true;
            }
        };

        $this->dispatcher->register($subscriber);
        $this->dispatcher->unregister($subscriber);
        $this->dispatcher->dispatch(EventMessage::create(new SampleDispatchEvent()));

        self::assertFalse($subscriber->called);
    }

    public function test_that_unregister_removes_array_with_priority_handler(): void
    {
        $subscriber = new class implements EventSubscriber {
            public bool $called = false;

            public static function eventRegistration(): array
            {
                return [SampleDispatchEvent::class => ['onEvent', 10]];
            }

            public function onEvent(EventMessage $m): void
            {
                $this->called = true;
            }
        };

        $this->dispatcher->register($subscriber);
        $this->dispatcher->unregister($subscriber);
        $this->dispatcher->dispatch(EventMessage::create(new SampleDispatchEvent()));

        self::assertFalse($subscriber->called);
    }

    public function test_that_unregister_removes_array_of_handlers_format(): void
    {
        $subscriber = new class implements EventSubscriber {
            public int $callCount = 0;

            public static function eventRegistration(): array
            {
                return [SampleDispatchEvent::class => [['onFirst', 10], ['onSecond']]];
            }

            public function onFirst(EventMessage $m): void
            {
                $this->callCount++;
            }

            public function onSecond(EventMessage $m): void
            {
                $this->callCount++;
            }
        };

        $this->dispatcher->register($subscriber);
        $this->dispatcher->unregister($subscriber);
        $this->dispatcher->dispatch(EventMessage::create(new SampleDispatchEvent()));

        self::assertSame(0, $subscriber->callCount);
    }

    public function test_that_add_handler_and_get_handlers_return_it(): void
    {
        $handler = function (): void {};
        $this->dispatcher->addHandler('some_event', $handler);

        $handlers = $this->dispatcher->getHandlers('some_event');

        self::assertCount(1, $handlers);
        self::assertSame($handler, $handlers[0]);
    }

    public function test_that_get_handlers_returns_empty_for_unknown_type(): void
    {
        self::assertSame([], $this->dispatcher->getHandlers('no_such_event'));
    }

    public function test_that_get_handlers_with_null_returns_all_registered(): void
    {
        $this->dispatcher->addHandler('event_a', function (): void {});
        $this->dispatcher->addHandler('event_b', function (): void {});

        $all = $this->dispatcher->getHandlers();

        self::assertCount(2, $all);
    }

    public function test_that_get_handlers_null_returns_empty_when_nothing_registered(): void
    {
        self::assertSame([], $this->dispatcher->getHandlers());
    }

    public function test_that_has_handlers_returns_false_initially(): void
    {
        self::assertFalse($this->dispatcher->hasHandlers());
        self::assertFalse($this->dispatcher->hasHandlers('some_event'));
    }

    public function test_that_has_handlers_returns_true_after_registration(): void
    {
        $this->dispatcher->addHandler('my_event', function (): void {});

        self::assertTrue($this->dispatcher->hasHandlers());
        self::assertTrue($this->dispatcher->hasHandlers('my_event'));
        self::assertFalse($this->dispatcher->hasHandlers('other_event'));
    }

    public function test_that_remove_handler_is_no_op_for_unknown_event(): void
    {
        $this->dispatcher->removeHandler('nonexistent', function (): void {});
        self::assertFalse($this->dispatcher->hasHandlers());
    }

    public function test_that_remove_handler_removes_specific_handler(): void
    {
        $handler1 = function (): void {};
        $handler2 = function (): void {};
        $this->dispatcher->addHandler('my_event', $handler1);
        $this->dispatcher->addHandler('my_event', $handler2);

        $this->dispatcher->removeHandler('my_event', $handler1);

        $handlers = $this->dispatcher->getHandlers('my_event');
        self::assertCount(1, $handlers);
        self::assertSame($handler2, $handlers[0]);
    }

    public function test_that_remove_handler_cleans_up_empty_priority_and_event(): void
    {
        $handler = function (): void {};
        $this->dispatcher->addHandler('my_event', $handler);

        $this->dispatcher->removeHandler('my_event', $handler);

        self::assertFalse($this->dispatcher->hasHandlers('my_event'));
        self::assertSame([], $this->dispatcher->getHandlers());
    }

    public function test_that_handlers_are_sorted_by_priority_descending(): void
    {
        $calls = [];
        $key = ClassName::underscore(SampleDispatchEvent::class);
        $this->dispatcher->addHandler($key, function () use (&$calls): void { $calls[] = 'low'; }, 0);
        $this->dispatcher->addHandler($key, function () use (&$calls): void { $calls[] = 'high'; }, 10);
        $this->dispatcher->addHandler($key, function () use (&$calls): void { $calls[] = 'mid'; }, 5);

        $this->dispatcher->dispatch(EventMessage::create(new SampleDispatchEvent()));

        self::assertSame(['high', 'mid', 'low'], $calls);
    }

    public function test_that_sorted_cache_is_invalidated_on_new_add_handler(): void
    {
        $handler1 = function (): void {};
        $handler2 = function (): void {};

        $this->dispatcher->addHandler('my_event', $handler1);
        $this->dispatcher->getHandlers('my_event');

        $this->dispatcher->addHandler('my_event', $handler2, 5);

        $handlers = $this->dispatcher->getHandlers('my_event');

        self::assertCount(2, $handlers);
        self::assertSame($handler2, $handlers[0]);
    }
}

class SampleDispatchEvent implements Event
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

final class FailureDispatchCallLog
{
    /** @var string[] */
    public static array $calls = [];

    public static ?\Throwable $functionFailure = null;

    public static function reset(): void
    {
        self::$calls = [];
        self::$functionFailure = null;
    }

    public static function record(string $call): void
    {
        self::$calls[] = $call;
    }
}

final readonly class FailureDispatchHandler
{
    public function __construct(private \Throwable $failure)
    {
    }

    public function onEvent(): void
    {
        FailureDispatchCallLog::record('object');
        throw $this->failure;
    }
}

final readonly class InvokableFailureDispatchHandler
{
    public function __construct(private \Throwable $failure)
    {
    }

    public function __invoke(): void
    {
        FailureDispatchCallLog::record('invokable');
        throw $this->failure;
    }
}

final class StaticFailureDispatchHandler
{
    public static ?\Throwable $failure = null;

    public static function onEvent(): void
    {
        FailureDispatchCallLog::record('static');
        throw self::$failure ?? new RuntimeException('Missing static failure.');
    }
}

function sample_failing_dispatch_handler(): void
{
    FailureDispatchCallLog::record('function');
    throw FailureDispatchCallLog::$functionFailure ?? new RuntimeException('Missing function failure.');
}
