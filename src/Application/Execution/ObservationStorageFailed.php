<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution;

use RuntimeException;
use Throwable;

/**
 * Class ObservationStorageFailed
 *
 * The previous throwable is private diagnostic material, never public output
 */
final class ObservationStorageFailed extends RuntimeException
{
    /**
     * Constructs ObservationStorageFailed
     */
    public function __construct(public readonly bool $outcomeUncertain, Throwable $previous)
    {
        parent::__construct(
            $outcomeUncertain ? 'Observation storage outcome is uncertain.' : 'Observation storage was rolled back.',
            0,
            $previous
        );
    }
}
