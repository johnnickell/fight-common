<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\EventSourcing;

use DateTimeImmutable;
use Fight\Common\Adapter\EventSourcing\InMemory\InMemoryEventRecord;
use Fight\Common\Adapter\EventSourcing\InMemory\InMemoryEventStore;
use Fight\Common\Adapter\EventSourcing\InMemory\InMemoryPublicationCursorStore;
use Fight\Common\Adapter\EventSourcing\InMemory\InMemoryPublicationFailureRecorder;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Application\EventSourcing\EventPublicationFailure;
use Fight\Common\Application\EventSourcing\EventPublicationRunner;
use Fight\Common\Application\EventSourcing\PublicationCursorStore;
use Fight\Common\Application\EventSourcing\PublicationFailureRecorder;
use Fight\Common\Domain\EventSourcing\EventMapper;
use Fight\Common\Domain\EventSourcing\EventMapping;
use Fight\Common\Domain\EventSourcing\EventMappingProvider;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(EventPublicationRunner::class)]
final class EventPublicationRunnerTest extends UnitTestCase
{
    public function test_that_a_named_publication_dispatches_one_bounded_batch_after_its_cursor(): void
    {
        $eventStore = new InMemoryEventStore(
            new EventMapper([new PublicationEventMappingProvider()]),
            [
                $this->eventRecord(1, 'order-1', '6ba7b841-9dad-11d1-80b4-00c04fd430c8'),
                $this->eventRecord(2, 'order-2', '6ba7b842-9dad-11d1-80b4-00c04fd430c8'),
                $this->eventRecord(3, 'order-3', '6ba7b843-9dad-11d1-80b4-00c04fd430c8'),
                $this->eventRecord(4, 'order-4', '6ba7b844-9dad-11d1-80b4-00c04fd430c8'),
            ],
        );
        $dispatcher = new SimpleEventDispatcher();
        $dispatched = [];
        $observedCursors = [];
        $cursorStore = new InMemoryPublicationCursorStore();
        $cursorStore->save('orders.subscribers', 1);

        $dispatcher->addHandler(
            ClassName::underscore(PublishedOrderPlaced::class),
            static function (EventMessage $message) use (&$dispatched, &$observedCursors, $cursorStore): void {
                $dispatched[] = $message;
                $observedCursors[] = $cursorStore->load('orders.subscribers');
            },
        );
        $runner = new EventPublicationRunner(
            'orders.subscribers',
            $eventStore,
            $dispatcher,
            $cursorStore,
            new InMemoryPublicationFailureRecorder(),
        );

        $runner->run(2);

        self::assertSame(
            ['order-2', 'order-3'],
            array_map(
                static fn (EventMessage $message): string => $message->payload()->toArray()['order_id'],
                $dispatched,
            ),
        );
        self::assertSame(
            [
                '6ba7b842-9dad-11d1-80b4-00c04fd430c8',
                '6ba7b843-9dad-11d1-80b4-00c04fd430c8',
            ],
            array_map(
                static fn (EventMessage $message): string => $message->id()->toString(),
                $dispatched,
            ),
        );
        self::assertSame([1, 2], $observedCursors);
        self::assertSame(3, $cursorStore->load('orders.subscribers'));
    }

    public function test_that_completed_fanout_failures_are_recorded_once_before_cursor_advancement(): void
    {
        $eventStore = new InMemoryEventStore(
            new EventMapper([new PublicationEventMappingProvider()]),
            [$this->eventRecord(1, 'order-1', '6ba7b841-9dad-11d1-80b4-00c04fd430c8')],
        );
        $dispatcher = new SimpleEventDispatcher();
        $failingHandlerCalls = 0;
        $completedHandlerCalls = 0;
        $eventType = ClassName::underscore(PublishedOrderPlaced::class);
        $dispatcher->addHandler(
            $eventType,
            static function () use (&$failingHandlerCalls): void {
                $failingHandlerCalls++;

                throw new RuntimeException('First subscriber failed.', 41);
            },
            30,
        );
        $dispatcher->addHandler(
            $eventType,
            static function () use (&$failingHandlerCalls): void {
                $failingHandlerCalls++;

                throw new LogicException('Second subscriber failed.', 42);
            },
            20,
        );
        $dispatcher->addHandler(
            $eventType,
            static function () use (&$completedHandlerCalls): void {
                $completedHandlerCalls++;
            },
            10,
        );
        $cursorStore = new InMemoryPublicationCursorStore();
        $failureRecorder = new InMemoryPublicationFailureRecorder();
        $runner = new EventPublicationRunner(
            'orders.subscribers',
            $eventStore,
            $dispatcher,
            $cursorStore,
            $failureRecorder,
        );
        $earliestAttemptTime = new DateTimeImmutable();

        $runner->run(1);
        $latestAttemptTime = new DateTimeImmutable();
        $runner->run(1);

        self::assertSame(2, $failingHandlerCalls);
        self::assertSame(1, $completedHandlerCalls);
        self::assertSame(1, $cursorStore->load('orders.subscribers'));
        self::assertCount(1, $failureRecorder->failures());

        $failure = $failureRecorder->failures()[0];
        self::assertSame('orders.subscribers', $failure->publicationName());
        self::assertSame(1, $failure->globalPosition());
        self::assertGreaterThanOrEqual($earliestAttemptTime, $failure->dispatchStartedAt());
        self::assertLessThanOrEqual($latestAttemptTime, $failure->dispatchStartedAt());
        self::assertSame(
            [RuntimeException::class, LogicException::class],
            array_map(
                static fn ($handlerFailure): string => $handlerFailure->exceptionClass(),
                $failure->handlerFailures(),
            ),
        );
        self::assertSame(
            [41, 42],
            array_map(
                static fn ($handlerFailure): int => $handlerFailure->exceptionCode(),
                $failure->handlerFailures(),
            ),
        );
    }

    public function test_that_an_unexpected_dispatcher_failure_propagates_without_cursor_advancement(): void
    {
        $eventStore = new InMemoryEventStore(
            new EventMapper([new PublicationEventMappingProvider()]),
            [$this->eventRecord(1, 'order-1', '6ba7b841-9dad-11d1-80b4-00c04fd430c8')],
        );
        $failure = new RuntimeException('Subscriber resolution failed.');
        $dispatcher = new class($failure) extends SimpleEventDispatcher {
            public function __construct(private readonly RuntimeException $failure)
            {
            }

            public function dispatch(EventMessage $eventMessage): void
            {
                throw $this->failure;
            }
        };
        $cursorStore = new InMemoryPublicationCursorStore();
        $failureRecorder = new InMemoryPublicationFailureRecorder();
        $runner = new EventPublicationRunner(
            'orders.subscribers',
            $eventStore,
            $dispatcher,
            $cursorStore,
            $failureRecorder,
        );

        try {
            $runner->run(1);
            self::fail('Expected the unexpected dispatcher failure to propagate.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($failure, $runtimeException);
        }

        self::assertSame(0, $cursorStore->load('orders.subscribers'));
        self::assertSame([], $failureRecorder->failures());
    }

    public function test_that_a_recorder_failure_propagates_before_any_cursor_save(): void
    {
        $eventStore = new InMemoryEventStore(
            new EventMapper([new PublicationEventMappingProvider()]),
            [$this->eventRecord(1, 'order-1', '6ba7b841-9dad-11d1-80b4-00c04fd430c8')],
        );
        $dispatcher = new SimpleEventDispatcher();
        $dispatcher->addHandler(
            ClassName::underscore(PublishedOrderPlaced::class),
            static function (): void {
                throw new RuntimeException('Subscriber failed.');
            },
        );
        $calls = new PublicationCollaboratorCallLog();
        $cursorStore = new readonly class($calls) implements PublicationCursorStore {
            public function __construct(private PublicationCollaboratorCallLog $calls)
            {
            }

            public function load(string $publicationName): int
            {
                return 0;
            }

            public function save(string $publicationName, int $globalPosition): void
            {
                $this->calls->calls[] = 'save';
            }
        };
        $failure = new RuntimeException('Failure recording unavailable.');
        $failureRecorder = new readonly class($calls, $failure) implements PublicationFailureRecorder {
            public function __construct(
                private PublicationCollaboratorCallLog $calls,
                private RuntimeException $failure,
            ) {
            }

            public function record(EventPublicationFailure $failure): void
            {
                $this->calls->calls[] = 'record';

                throw $this->failure;
            }
        };
        $runner = new EventPublicationRunner(
            'orders.subscribers',
            $eventStore,
            $dispatcher,
            $cursorStore,
            $failureRecorder,
        );

        try {
            $runner->run(1);
            self::fail('Expected the recorder failure to propagate.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($failure, $runtimeException);
        }

        self::assertSame(['record'], $calls->calls);
        self::assertSame(0, $cursorStore->load('orders.subscribers'));
    }

    public function test_that_a_cursor_save_failure_propagates_and_retry_may_duplicate_delivery(): void
    {
        $eventStore = new InMemoryEventStore(
            new EventMapper([new PublicationEventMappingProvider()]),
            [$this->eventRecord(1, 'order-1', '6ba7b841-9dad-11d1-80b4-00c04fd430c8')],
        );
        $dispatcher = new SimpleEventDispatcher();
        $subscriberCalls = 0;
        $dispatcher->addHandler(
            ClassName::underscore(PublishedOrderPlaced::class),
            static function () use (&$subscriberCalls): void {
                $subscriberCalls++;

                throw new RuntimeException('Subscriber failed after its external effect.', 41);
            },
        );
        $calls = new PublicationCollaboratorCallLog();
        $cursorFailure = new RuntimeException('Cursor save unavailable.');
        $cursorStore = new FailingOncePublicationCursorStore($calls, $cursorFailure);
        $failureRecorder = new RecordingPublicationFailureRecorder($calls);
        $runner = new EventPublicationRunner(
            'orders.subscribers',
            $eventStore,
            $dispatcher,
            $cursorStore,
            $failureRecorder,
        );

        try {
            $runner->run(1);
            self::fail('Expected the first cursor save to fail.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($cursorFailure, $runtimeException);
        }

        self::assertSame(1, $subscriberCalls);
        self::assertSame(0, $cursorStore->load('orders.subscribers'));
        self::assertSame(['record', 'save'], $calls->calls);
        self::assertCount(1, $failureRecorder->failures());

        $runner->run(1);

        self::assertSame(2, $subscriberCalls);
        self::assertSame(1, $cursorStore->load('orders.subscribers'));
        self::assertSame(['record', 'save', 'record', 'save'], $calls->calls);
        self::assertCount(1, $failureRecorder->failures());
    }

    public function test_that_independent_publication_names_keep_independent_progress_and_failure_identity(): void
    {
        $eventStore = new InMemoryEventStore(
            new EventMapper([new PublicationEventMappingProvider()]),
            [$this->eventRecord(1, 'order-1', '6ba7b841-9dad-11d1-80b4-00c04fd430c8')],
        );
        $dispatcher = new SimpleEventDispatcher();
        $subscriberCalls = 0;
        $dispatcher->addHandler(
            ClassName::underscore(PublishedOrderPlaced::class),
            static function () use (&$subscriberCalls): void {
                $subscriberCalls++;

                throw new RuntimeException('Subscriber failed.', 41);
            },
        );
        $cursorStore = new InMemoryPublicationCursorStore();
        $failureRecorder = new InMemoryPublicationFailureRecorder();
        $primaryRunner = new EventPublicationRunner(
            'orders.primary',
            $eventStore,
            $dispatcher,
            $cursorStore,
            $failureRecorder,
        );
        $secondaryRunner = new EventPublicationRunner(
            'orders.secondary',
            $eventStore,
            $dispatcher,
            $cursorStore,
            $failureRecorder,
        );

        $primaryRunner->run(1);
        $secondaryRunner->run(1);
        $primaryRunner->run(1);

        self::assertSame(2, $subscriberCalls);
        self::assertSame(1, $cursorStore->load('orders.primary'));
        self::assertSame(1, $cursorStore->load('orders.secondary'));
        self::assertSame(
            [
                ['orders.primary', 1],
                ['orders.secondary', 1],
            ],
            array_map(
                static fn (EventPublicationFailure $failure): array => [
                    $failure->publicationName(),
                    $failure->globalPosition(),
                ],
                $failureRecorder->failures(),
            ),
        );
    }

    private function eventRecord(int $globalPosition, string $orderId, string $messageId): InMemoryEventRecord
    {
        return new InMemoryEventRecord(
            new StreamId('order', $orderId),
            'orders.order-placed',
            1,
            1,
            $globalPosition,
            ['order_id' => $orderId],
            MessageId::fromString($messageId),
            new DateTimeImmutable('2026-08-09T09:15:30.123456+00:00'),
            [],
        );
    }
}

final readonly class PublicationEventMappingProvider implements EventMappingProvider
{
    public function namespace(): string
    {
        return 'orders';
    }

    public function mappings(): iterable
    {
        yield new EventMapping('order-placed', PublishedOrderPlaced::class, 1);
    }
}

final readonly class PublishedOrderPlaced implements Event
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

final class PublicationCollaboratorCallLog
{
    /** @var list<string> */
    public array $calls = [];
}

final class FailingOncePublicationCursorStore implements PublicationCursorStore
{
    private int $cursor = 0;

    private bool $failed = false;

    public function __construct(
        private readonly PublicationCollaboratorCallLog $calls,
        private readonly RuntimeException $failure,
    ) {
    }

    public function load(string $publicationName): int
    {
        return $this->cursor;
    }

    public function save(string $publicationName, int $globalPosition): void
    {
        $this->calls->calls[] = 'save';

        if (!$this->failed) {
            $this->failed = true;

            throw $this->failure;
        }

        $this->cursor = $globalPosition;
    }
}

final readonly class RecordingPublicationFailureRecorder implements PublicationFailureRecorder
{
    private InMemoryPublicationFailureRecorder $recorder;

    public function __construct(private PublicationCollaboratorCallLog $calls)
    {
        $this->recorder = new InMemoryPublicationFailureRecorder();
    }

    public function record(EventPublicationFailure $failure): void
    {
        $this->calls->calls[] = 'record';
        $this->recorder->record($failure);
    }

    /** @return list<EventPublicationFailure> */
    public function failures(): array
    {
        return $this->recorder->failures();
    }
}
