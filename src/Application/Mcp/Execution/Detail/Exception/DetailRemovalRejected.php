<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Detail\Exception;

use RuntimeException;

/**
 * Class DetailRemovalRejected
 *
 * Conceals denied, unknown and inconsistent selected relationships
 */
final class DetailRemovalRejected extends RuntimeException
{
    /**
     * Constructs DetailRemovalRejected
     */
    public function __construct()
    {
        parent::__construct('Captured detail removal was not admitted.');
    }
}
