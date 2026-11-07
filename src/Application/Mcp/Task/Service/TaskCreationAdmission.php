<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\Service;

use Fight\Common\Domain\Mcp\Task\TaskDefinition;

/**
 * Interface TaskCreationAdmission
 */
interface TaskCreationAdmission
{
    /**
     * Returns current creation authority from trusted consumer context including retries
     */
    public function allowsCreation(TaskDefinition $definition): bool;
}
