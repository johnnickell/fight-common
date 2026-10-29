<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Observation\Exception;

use RuntimeException;

/**
 * Class ProtectedContentRemoved
 */
final class ProtectedContentRemoved extends RuntimeException
{
    /**
     * Constructs ProtectedContentRemoved
     */
    public function __construct()
    {
        parent::__construct('Protected comparison content is no longer retained.');
    }
}
