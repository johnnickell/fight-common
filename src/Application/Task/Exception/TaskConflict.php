<?php

declare(strict_types=1);

namespace Fight\Common\Application\Task\Exception;

use RuntimeException;

/**
 * Class TaskConflict
 */
final class TaskConflict extends RuntimeException
{
    /**
     * Constructs TaskConflict
     */
    public function __construct()
    {
        parent::__construct('Task state conflicts.');
    }
}
