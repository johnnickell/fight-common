<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\Exception;

use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\Event\TaskDataRemoved;
use RuntimeException;
use Throwable;

/**
 * Class CommittedTaskDispatchFailed
 */
final class CommittedTaskDispatchFailed extends RuntimeException
{
    /**
     * Constructs CommittedTaskDispatchFailed
     */
    public function __construct(private readonly TaskChanged|TaskDataRemoved $event, Throwable $cause)
    {
        parent::__construct('Task committed; fact dispatch failed.', 0, $cause);
    }

    /**
     * Returns the reference-only fact without pretending dispatch failure rolled back state
     */
    public function event(): TaskChanged|TaskDataRemoved
    {
        return $this->event;
    }
}
