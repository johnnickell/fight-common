<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release\Boundary;

use RuntimeException;

/**
 * Class ReleaseBoundaryCrash
 *
 * Signals a deliberately abrupt deterministic boundary interruption in tests.
 */
final class ReleaseBoundaryCrash extends RuntimeException
{
    /**
     * Constructs ReleaseBoundaryCrash
     */
    public function __construct(public readonly string $effectClass)
    {
        parent::__construct('Deterministic release boundary crashed at '.$effectClass.'.');
    }
}
