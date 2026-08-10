<?php

declare(strict_types=1);

namespace Fight\Common\Domain\EventSourcing;

/**
 * Interface EventMappingProvider
 *
 * Provides typed event mappings under one durable namespace
 */
interface EventMappingProvider
{
    /**
     * Returns the durable event namespace
     */
    public function namespace(): string;

    /**
     * Returns the locally named event mappings
     *
     * @return iterable<EventMapping>
     */
    public function mappings(): iterable;
}
