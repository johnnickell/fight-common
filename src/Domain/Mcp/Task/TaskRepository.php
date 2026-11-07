<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Task;

/**
 * Interface TaskRepository
 *
 * Owns coherent aggregate persistence inside the separately composed serialized mutation boundary
 */
interface TaskRepository
{
    /**
     * Returns coherent protected state under the owned mutation transaction
     */
    public function getById(string $scope, TaskId $id): ?Task;

    /**
     * Writes the complete current state atomically under the owned transaction
     */
    public function save(Task $task): void;

    /**
     * Removes whole rows and comparison material under the owned mutation transaction
     *
     * Returns only actually removed identities, or null for an entirely missing selection
     */
    public function remove(TaskSelection $selection): ?TaskSelection;
}
