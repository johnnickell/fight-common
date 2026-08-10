<?php

declare(strict_types=1);

namespace Fight\Common\Application\EventSourcing;

use Fight\Common\Domain\EventSourcing\StoredEvent;
use Fight\Common\Domain\Messaging\Event\Event;

/**
 * Interface Projector
 *
 * Builds one read model from already-upcasted stored events
 *
 * Projection operations must be idempotent because at-least-once delivery can
 * repeat an event when read-state persistence succeeds before its checkpoint.
 */
interface Projector
{
    /**
     * Returns the stable identity used to persist projection progress
     */
    public function name(): string;

    /**
     * Returns the current event payload classes handled by this projector
     *
     * @return iterable<class-string<Event>>
     */
    public function eventClasses(): iterable;

    /**
     * Projects one already-upcasted stored event
     */
    public function project(StoredEvent $event): void;
}
