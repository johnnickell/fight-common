<?php

declare(strict_types=1);

namespace Fight\Common\Application\EventSourcing;

use DateTimeImmutable;
use DateTimeZone;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Domain\EventSourcing\StoredEvent;
use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Messaging\MessageId;

/**
 * Class EventPublicationFailure
 *
 * Portable snapshot of one completed event-publication attempt with failures
 */
final readonly class EventPublicationFailure
{
    /**
     * Constructs EventPublicationFailure
     *
     * @param string            $publicationName   Stable publication name.
     * @param StreamId          $streamId          Published stream identity.
     * @param string            $eventName         Stable stored event name.
     * @param integer           $schemaVersion     Persisted payload schema version.
     * @param integer           $streamVersion     Position within the event stream.
     * @param integer           $globalPosition    Position in global event order.
     * @param MessageId         $messageId         Published message identity.
     * @param DateTimeImmutable $dispatchStartedAt UTC dispatch-start time.
     * @param array             $handlerFailures
     *
     * @phpstan-param list<EventPublicationHandlerFailure> $handlerFailures
     */
    private function __construct(
        private string $publicationName,
        private StreamId $streamId,
        private string $eventName,
        private int $schemaVersion,
        private int $streamVersion,
        private int $globalPosition,
        private MessageId $messageId,
        private DateTimeImmutable $dispatchStartedAt,
        private array $handlerFailures
    ) {
    }

    /**
     * Creates a portable snapshot of a completed synchronous dispatch failure
     */
    public static function fromDispatchFailure(
        string $publicationName,
        StoredEvent $storedEvent,
        DateTimeImmutable $dispatchStartedAt,
        EventDispatchFailed $dispatchFailure
    ): self {
        return new self(
            $publicationName,
            new StreamId(
                $storedEvent->streamId()->aggregateName(),
                $storedEvent->streamId()->identifier()
            ),
            $storedEvent->eventName(),
            $storedEvent->schemaVersion(),
            $storedEvent->streamVersion(),
            $storedEvent->globalPosition(),
            MessageId::fromString($storedEvent->message()->id()->toString()),
            $dispatchStartedAt->setTimezone(new DateTimeZone('UTC')),
            array_map(
                EventPublicationHandlerFailure::fromHandlerFailure(...),
                $dispatchFailure->failures()
            )
        );
    }

    /**
     * Returns the stable publication name
     */
    public function publicationName(): string
    {
        return $this->publicationName;
    }

    /**
     * Returns the published event stream identity
     */
    public function streamId(): StreamId
    {
        return $this->streamId;
    }

    /**
     * Returns the stable stored event name
     */
    public function eventName(): string
    {
        return $this->eventName;
    }

    /**
     * Returns the persisted event schema version
     */
    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }

    /**
     * Returns the event position within its stream
     */
    public function streamVersion(): int
    {
        return $this->streamVersion;
    }

    /**
     * Returns the event position in global order
     */
    public function globalPosition(): int
    {
        return $this->globalPosition;
    }

    /**
     * Returns the published message identity
     */
    public function messageId(): MessageId
    {
        return $this->messageId;
    }

    /**
     * Returns the UTC dispatch-start time with microsecond precision
     */
    public function dispatchStartedAt(): DateTimeImmutable
    {
        return $this->dispatchStartedAt;
    }

    /**
     * Returns ordered handler-failure snapshots
     *
     * @return list<EventPublicationHandlerFailure>
     */
    public function handlerFailures(): array
    {
        return $this->handlerFailures;
    }
}
