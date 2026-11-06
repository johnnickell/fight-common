<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Task\Dbal;

use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use InvalidArgumentException;

/**
 * Class DbalTaskTables
 *
 * Explicit shared schema/runtime selection without database effects
 */
final readonly class DbalTaskTables
{
    private DbalTableName $stateTable;
    private DbalTableName $taskTable;

    /**
     * Constructs DbalTaskTables
     */
    public function __construct(string $stateTable = 'fight_task_state', string $taskTable = 'fight_tasks')
    {
        $this->stateTable = new DbalTableName($stateTable);
        $this->taskTable = new DbalTableName($taskTable);
        // Reject even case-only collisions for portable composition on MySQL.
        if (strtolower($stateTable) === strtolower($taskTable)) {
            throw new InvalidArgumentException('Task tables must have distinct names.');
        }
    }

    /**
     * Returns the selected schema state and serialization table
     */
    public function stateTable(): DbalTableName
    {
        return $this->stateTable;
    }

    /**
     * Returns the selected durable Task table
     */
    public function taskTable(): DbalTableName
    {
        return $this->taskTable;
    }
}
