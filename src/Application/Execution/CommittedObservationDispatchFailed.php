<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution;

use Fight\Common\Domain\Execution\ObservationRecorded;
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
    public function __construct(public readonly ObservationRecorded $event, Throwable $previous)
    {
        parent::__construct('Observation committed but event dispatch failed.', 0, $previous);
    }
}
