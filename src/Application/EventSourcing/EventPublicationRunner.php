<?php

declare(strict_types=1);

namespace Fight\Common\Application\EventSourcing;

use DateTimeImmutable;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Fight\Common\Domain\EventSourcing\EventStore;

/**
 * Class EventPublicationRunner
 *
 * Publishes one bounded batch of committed stored events
 */
final readonly class EventPublicationRunner
{
    /**
     * Constructs EventPublicationRunner
     */
    public function __construct(
        private string $publicationName,
        private EventStore $eventStore,
        private SynchronousEventDispatcher $eventDispatcher,
        private PublicationCursorStore $cursorStore,
        private PublicationFailureRecorder $failureRecorder,
    ) {
    }

    /**
     * Dispatches a bounded stored-event batch, records completed fan-out failures, and advances its cursor
     */
    public function run(int $limit): void
    {
        $cursor = $this->cursorStore->load($this->publicationName);

        foreach ($this->eventStore->readAllAfter($cursor, $limit) as $event) {
            $dispatchStartedAt = new DateTimeImmutable();

            try {
                $this->eventDispatcher->dispatch($event->message());
            } catch (EventDispatchFailed $dispatchFailure) {
                $this->failureRecorder->record(EventPublicationFailure::fromDispatchFailure(
                    $this->publicationName,
                    $event,
                    $dispatchStartedAt,
                    $dispatchFailure,
                ));
            }

            $this->cursorStore->save($this->publicationName, $event->globalPosition());
        }
    }
}
