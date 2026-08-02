<?php

declare(strict_types=1);

namespace Fight\Common\Domain\EventSourcing;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Stable identity of one event stream
 */
final readonly class StreamId
{
    /**
     * Constructs StreamId
     */
    public function __construct(
        private string $aggregateName,
        private string $identifier,
    ) {
        if ('' === $aggregateName) {
            throw new DomainException('Aggregate name cannot be empty.');
        }

        if ('' === $identifier) {
            throw new DomainException('Aggregate identifier cannot be empty.');
        }
    }

    /**
     * Returns the stable aggregate name
     */
    public function aggregateName(): string
    {
        return $this->aggregateName;
    }

    /**
     * Returns the aggregate identifier
     */
    public function identifier(): string
    {
        return $this->identifier;
    }
}
