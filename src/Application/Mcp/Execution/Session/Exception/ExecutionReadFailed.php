<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Session\Exception;

use RuntimeException;
use Throwable;

/**
 * Class ExecutionReadFailed
 */
final class ExecutionReadFailed extends RuntimeException
{
    /**
     * Constructs ExecutionReadFailed
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Execution evidence could not be read completely.', 0, $previous);
    }
}
