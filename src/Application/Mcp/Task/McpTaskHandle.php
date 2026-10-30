<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task;

use Fight\Common\Domain\Mcp\Task\TaskDefinition;

/**
 * Class McpTaskHandle
 */
final readonly class McpTaskHandle
{
    /**
     * Constructs McpTaskHandle
     */
    public function __construct(private string $id)
    {
        TaskDefinition::identity($id);
    }

    /**
     * Returns the identity without granting read or execution authority
     */
    public function id(): string
    {
        return $this->id;
    }
}
