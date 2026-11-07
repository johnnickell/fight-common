<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task;

use Fight\Common\Domain\Mcp\Task\TaskId;

/**
 * Class McpTaskHandle
 */
final readonly class McpTaskHandle
{
    /**
     * Constructs McpTaskHandle
     */
    public function __construct(private TaskId $id)
    {
    }

    /**
     * Returns the identity without granting read or execution authority
     */
    public function id(): TaskId
    {
        return $this->id;
    }
}
