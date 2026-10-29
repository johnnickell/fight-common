<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\Service;

use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskSelection;

/**
 * Interface TaskStore
 */
interface TaskStore extends TransactionalUnitOfWork
{
    /**
     * Returns coherent protected state under the owned mutation transaction
     */
    public function getById(string $scope, string $id): ?Task;

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

    /**
     * Returns committed protected state on an idle connection without mutation
     */
    public function read(string $scope, string $id): ?Task;
}
