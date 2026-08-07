<?php

declare(strict_types=1);

namespace Fight\Common\Domain\EventSourcing;

use Fight\Common\Domain\EventSourcing\Exception\OptimisticConcurrencyException;
use Fight\Common\Domain\Messaging\Event\EventMessage;

/**
 * Append-only storage boundary for mapped event messages.
 *
 * Every event returned by this store is committed with prefix-stable global
 * visibility: after global position N becomes visible, no event at a position
 * lower than N may become visible later.
 */
interface EventStore
{
    /**
     * Appends one ordered batch of event messages to a stream.
     *
     * The expected version is the version observed before the batch. An empty
     * stream has expected version zero, and its first stored event has stream
     * version one. Messages occupy consecutive stream positions in their given
     * order.
     *
     * An exact retry succeeds without another write only when every message ID
     * already occupies its intended consecutive position immediately after the
     * supplied expected version. Once every MessageId occupies its intended
     * consecutive position, the exact retry succeeds without comparing payload
     * or metadata content. A partial match, a misplaced or reordered ID, or an
     * ID found in another stream fails closed. When none of the message IDs exist
     * and the expected version is stale, the operation throws an
     * optimistic-concurrency failure.
     *
     * @param StreamId           $streamId       Stream receiving the event batch.
     * @param integer            $expectedVersion Stream version observed before the append.
     * @param list<EventMessage> $messages       Ordered event-message batch to append.
     *
     * @throws OptimisticConcurrencyException
     */
    public function append(StreamId $streamId, int $expectedVersion, array $messages): void;

    /**
     * Reads a stream in ascending stream-version order.
     *
     * A missing stream returns an empty iterable.
     *
     * @return iterable<StoredEvent>
     */
    public function readStream(StreamId $streamId): iterable;

    /**
     * Reads at most the requested limit strictly after a global position.
     *
     * Results contain only committed events and are returned in ascending
     * global-position order under the store's prefix-stable visibility
     * guarantee.
     *
     * @return iterable<StoredEvent>
     */
    public function readAllAfter(int $globalPosition, int $limit): iterable;
}
