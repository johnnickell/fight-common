<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Logging;

use DateTimeImmutable;
use Fight\Common\Adapter\EventSourcing\InMemory\InMemoryPublicationCursorStore;
use Fight\Common\Adapter\EventSourcing\Logging\LoggingPublicationFailureRecorder;
use Fight\Common\Application\EventSourcing\EventPublicationFailure;
use Fight\Common\Application\EventSourcing\EventPublicationRunner;
use Fight\Common\Application\EventSourcing\PublicationFailureRecorder;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Application\Messaging\Event\EventHandlerFailure;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Fight\Common\Domain\EventSourcing\EventStore;
use Fight\Common\Domain\EventSourcing\StoredEvent;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;

#[CoversClass(LoggingPublicationFailureRecorder::class)]
#[CoversClass(EventPublicationRunner::class)]
final class LoggingPublicationFailureRecorderTest extends UnitTestCase
{
    public function test_that_record_logs_the_complete_portable_snapshot_before_delegating_the_same_failure(): void
    {
        $failure = $this->failure();

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')->once()->globally()->ordered()->with(
            LogLevel::ERROR,
            '[Event Publication]: Failure',
            [
                'publication_name'     => 'orders.subscribers',
                'aggregate_name'       => 'order',
                'aggregate_identifier' => 'order-42',
                'event_name'           => 'orders.order-placed',
                'schema_version'       => 2,
                'stream_version'       => 7,
                'global_position'      => 23,
                'message_id'           => '6ba7b841-9dad-11d1-80b4-00c04fd430c8',
                'dispatch_started_at'  => '2026-08-09T09:15:30.123456+00:00',
                'handler_failures'     => [
                    [
                        'callable_description' => 'OrdersSubscriber::onOrderPlaced',
                        'exception_class'      => RuntimeException::class,
                        'exception_code'       => 73,
                        'diagnostic_message'   => 'First subscriber failed.',
                    ],
                    [
                        'callable_description' => 'Closure (non-replayable)',
                        'exception_class'      => LogicException::class,
                        'exception_code'       => 91,
                        'diagnostic_message'   => 'Second subscriber failed.',
                    ],
                ],
            ],
        );

        /** @var MockInterface|PublicationFailureRecorder $delegate */
        $delegate = $this->mock(PublicationFailureRecorder::class);
        $delegate->shouldReceive('record')->once()->globally()->ordered()->with(
            Mockery::on(static fn (EventPublicationFailure $recorded): bool => $recorded === $failure),
        );

        $recorder = new LoggingPublicationFailureRecorder($delegate, $logger);
        $recorder->record($failure);
    }

    public function test_that_record_uses_the_configured_log_level(): void
    {
        $failure = $this->failure();

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')->once()->with(
            LogLevel::WARNING,
            '[Event Publication]: Failure',
            Mockery::type('array'),
        );

        /** @var MockInterface|PublicationFailureRecorder $delegate */
        $delegate = $this->mock(PublicationFailureRecorder::class);
        $delegate->shouldReceive('record')->once()->with($failure);

        $recorder = new LoggingPublicationFailureRecorder($delegate, $logger, LogLevel::WARNING);
        $recorder->record($failure);
    }

    public function test_that_retry_logs_remain_correlatable_by_publication_name_and_global_position(): void
    {
        $failure = $this->failure();
        $correlationKeys = [];

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')->twice()->withArgs(
            static function (string $level, string $message, array $context) use (&$correlationKeys): bool {
                $correlationKeys[] = [$context['publication_name'], $context['global_position']];

                return LogLevel::ERROR === $level && '[Event Publication]: Failure' === $message;
            },
        );

        /** @var MockInterface|PublicationFailureRecorder $delegate */
        $delegate = $this->mock(PublicationFailureRecorder::class);
        $delegate->shouldReceive('record')->twice()->with($failure);

        $recorder = new LoggingPublicationFailureRecorder($delegate, $logger);
        $recorder->record($failure);
        $recorder->record($failure);

        self::assertSame(
            [
                ['orders.subscribers', 23],
                ['orders.subscribers', 23],
            ],
            $correlationKeys,
        );
    }

    public function test_that_a_logger_failure_propagates_without_delegation_or_cursor_advancement(): void
    {
        $loggerFailure = new RuntimeException('Publication logging unavailable.');

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')->once()->andThrow($loggerFailure);

        /** @var MockInterface|PublicationFailureRecorder $delegate */
        $delegate = $this->mock(PublicationFailureRecorder::class);
        $delegate->shouldNotReceive('record');

        [$runner, $cursorStore] = $this->runner(
            new LoggingPublicationFailureRecorder($delegate, $logger),
        );

        try {
            $runner->run(1);
            self::fail('Expected the logger failure to propagate.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($loggerFailure, $runtimeException);
        }

        self::assertSame(0, $cursorStore->load('orders.subscribers'));
    }

    public function test_that_a_delegate_failure_propagates_after_logging_without_cursor_advancement(): void
    {
        $delegateFailure = new RuntimeException('Publication recording unavailable.');

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')->once()->globally()->ordered();

        /** @var MockInterface|PublicationFailureRecorder $delegate */
        $delegate = $this->mock(PublicationFailureRecorder::class);
        $delegate->shouldReceive('record')->once()->globally()->ordered()->andThrow($delegateFailure);

        [$runner, $cursorStore] = $this->runner(
            new LoggingPublicationFailureRecorder($delegate, $logger),
        );

        try {
            $runner->run(1);
            self::fail('Expected the delegate failure to propagate.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($delegateFailure, $runtimeException);
        }

        self::assertSame(0, $cursorStore->load('orders.subscribers'));
    }

    /**
     * @return array{EventPublicationRunner, InMemoryPublicationCursorStore}
     */
    private function runner(PublicationFailureRecorder $recorder): array
    {
        $failure = $this->failure();
        $storedEvent = new StoredEvent(
            $failure->streamId(),
            $failure->eventName(),
            $failure->schemaVersion(),
            $failure->streamVersion(),
            $failure->globalPosition(),
            new EventMessage(
                $failure->messageId(),
                new DateTimeImmutable('2026-08-09T09:14:00.000001+00:00'),
                new LoggedPublicationOrderPlaced(),
                Meta::create(),
            ),
        );

        /** @var MockInterface|EventStore $eventStore */
        $eventStore = $this->mock(EventStore::class);
        $eventStore->shouldReceive('readAllAfter')->once()->with(0, 1)->andReturn([$storedEvent]);

        /** @var MockInterface|SynchronousEventDispatcher $dispatcher */
        $dispatcher = $this->mock(SynchronousEventDispatcher::class);
        $dispatcher->shouldReceive('dispatch')->once()->with($storedEvent->message())->andThrow(
            new EventDispatchFailed([
                new EventHandlerFailure(
                    'OrdersSubscriber::onOrderPlaced',
                    new RuntimeException('First subscriber failed.', 73),
                ),
            ]),
        );

        $cursorStore = new InMemoryPublicationCursorStore();

        return [
            new EventPublicationRunner(
                'orders.subscribers',
                $eventStore,
                $dispatcher,
                $cursorStore,
                $recorder,
            ),
            $cursorStore,
        ];
    }

    private function failure(): EventPublicationFailure
    {
        return EventPublicationFailure::fromDispatchFailure(
            'orders.subscribers',
            new StoredEvent(
                new StreamId('order', 'order-42'),
                'orders.order-placed',
                2,
                7,
                23,
                new EventMessage(
                    MessageId::fromString('6ba7b841-9dad-11d1-80b4-00c04fd430c8'),
                    new DateTimeImmutable('2026-08-09T09:14:00.000001+00:00'),
                    new LoggedPublicationOrderPlaced(),
                    Meta::create(['secret' => 'must-not-be-logged']),
                ),
            ),
            new DateTimeImmutable('2026-08-09T04:15:30.123456-05:00'),
            new EventDispatchFailed([
                new EventHandlerFailure(
                    'OrdersSubscriber::onOrderPlaced',
                    new RuntimeException('First subscriber failed.', 73),
                ),
                new EventHandlerFailure(
                    'Closure (non-replayable)',
                    new LogicException('Second subscriber failed.', 91),
                ),
            ]),
        );
    }
}

final readonly class LoggedPublicationOrderPlaced implements Event
{
    public static function fromArray(array $data): static
    {
        return new self();
    }

    public function toArray(): array
    {
        return ['secret_payload' => 'must-not-be-logged'];
    }
}
