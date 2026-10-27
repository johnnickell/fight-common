<?php

declare(strict_types=1);

namespace Fight\Common\Application\Task\Exception;

use RuntimeException;

/**
 * Class TaskRejected
 */
final class TaskRejected extends RuntimeException
{
    /**
     * Constructs TaskRejected
     */
    public function __construct()
    {
        parent::__construct('Task operation rejected.');
    }
}
