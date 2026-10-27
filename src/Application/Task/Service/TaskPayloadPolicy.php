<?php

declare(strict_types=1);

namespace Fight\Common\Application\Task\Service;

use Fight\Common\Domain\Task\Task;
use Fight\Common\Domain\Task\TaskDefinition;
use Fight\Common\Domain\Task\TaskState;

/**
 * Interface TaskPayloadPolicy
 */
interface TaskPayloadPolicy
{
    /**
     * Validates supported original contract context and safe producer evidence without execution
     *
     * Implementations reject unsupported contexts, input forms, mismatched original results and
     * unmapped errors. A Tool isError result remains completed, not a JSON-RPC execution failure.
     * JSON validity is not redaction. Failures must not expose supplied payloads or diagnostics.
     */
    public function validate(TaskDefinition $definition, TaskState $state): void;

    /**
     * Returns current permission and safety for every projected field or rejects the whole view
     *
     * Status, outstanding input, results and errors require independent protection where needed.
     * Implementations must not manufacture an empty successful result to conceal protected data.
     */
    public function mayDisclose(Task $task): bool;
}
