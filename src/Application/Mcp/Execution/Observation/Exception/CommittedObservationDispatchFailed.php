<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Observation\Exception;

use Fight\Common\Domain\Mcp\Observation\Event\ObservationRecorded;
use RuntimeException;
use Throwable;

/**
 * Class CommittedObservationDispatchFailed
 *
 * Storage committed; the previous throwable is private dispatch diagnostics
 */
final class CommittedObservationDispatchFailed extends RuntimeException
{
    /**
     * Constructs CommittedObservationDispatchFailed
     */
    public function __construct(private readonly ObservationRecorded $event, Throwable $previous)
    {
        parent::__construct('Observation committed but event dispatch failed.', 0, $previous);
    }

    /**
     * Returns the safe committed observation fact
     */
    public function event(): ObservationRecorded
    {
        return $this->event;
    }
}
