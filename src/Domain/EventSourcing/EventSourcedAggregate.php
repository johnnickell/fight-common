<?php

declare(strict_types=1);

namespace Fight\Common\Domain\EventSourcing;

use Fight\Common\Domain\Identity\Identifier;
use Fight\Common\Domain\Messaging\Event\Event;

/**
 * Interface EventSourcedAggregate
 */
interface EventSourcedAggregate
{
    /**
     * Reconstitutes an aggregate from ordered event history
     *
     * @param iterable $events
     *
     * @phpstan-param iterable<Event> $events
     */
    public static function reconstitute(iterable $events): static;

    /**
     * Retrieves the aggregate identifier
     */
    public function id(): Identifier;

    /**
     * Retrieves the number of events applied to the aggregate
     */
    public function version(): int;

    /**
     * Releases newly recorded events
     *
     * @return list<Event>
     */
    public function releaseEvents(): array;
}
