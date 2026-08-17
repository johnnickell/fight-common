<?php

declare(strict_types=1);

namespace Fight\Common\Domain\EventSourcing\Exception;

use Fight\Common\Domain\EventSourcing\StreamId;
use Fight\Common\Domain\Exception\DomainException;

/**
 * Class OptimisticConcurrencyException
 */
final class OptimisticConcurrencyException extends DomainException
{
    /**
     * Constructs OptimisticConcurrencyException
     */
    public function __construct(
        private readonly StreamId $streamId,
        private readonly int $expectedVersion,
        private readonly int $actualVersion
    ) {
        parent::__construct(sprintf(
            'Optimistic concurrency conflict for stream "%s"/"%s": expected version %d, actual version %d',
            $streamId->aggregateName(),
            $streamId->identifier(),
            $expectedVersion,
            $actualVersion
        ));
    }

    /**
     * Returns the conflicting stream identity
     */
    public function streamId(): StreamId
    {
        return $this->streamId;
    }

    /**
     * Returns the expected stream version
     */
    public function expectedVersion(): int
    {
        return $this->expectedVersion;
    }

    /**
     * Returns the actual stream version
     */
    public function actualVersion(): int
    {
        return $this->actualVersion;
    }
}
