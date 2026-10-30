<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\Service;

use Closure;
use Fight\Common\Application\Mcp\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\Exception\TaskStorageFailed;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Value\Basic\StrictJson;
use RuntimeException;
use Throwable;

/**
 * Class TaskCancellationLifecycle
 */
final readonly class TaskCancellationLifecycle
{
    /**
     * Constructs TaskCancellationLifecycle
     *
     * @phpstan-param Closure(): int $clock Trusted server epoch-millisecond clock
     */
    public function __construct(
        private TaskStore $store,
        private TaskCancellationAdmission $admission,
        private EventDispatcher $events,
        private Closure $clock
    ) {
    }

    /**
     * Records cooperative intent before acknowledgment without invoking consumer stopping
     */
    public function request(RequestTaskCancellation $command): void
    {
        $changed = $this->store->commitTransactional(function () use ($command): ?Task {
            try {
                $allowed = $this->admission->allowsCancellation($command->scope, $command->id, $command->caller);
            } catch (Throwable) {
                throw new TaskRejected();
            }

            if (!$allowed) {
                throw new TaskRejected();
            }

            $task = $this->store->getById($command->scope, $command->id);
            $now = Task::timestamp(($this->clock)());
            if ($task === null || !$task->availableAt($now)) {
                throw new TaskRejected();
            }

            try {
                $next = $task->requestCancellation($now);
            } catch (DomainException) {
                throw new TaskConflict();
            }

            if ($next !== null) {
                $this->store->save($next);
            }

            return $next;
        });
        if ($changed === null) {
            return;
        }

        // Reconstruct through the shared committed read contract, not an in-memory write receipt.
        // Removal/expiry racing this check withholds acknowledgment, never claims a rolled-back intent.
        try {
            $retained = $this->store->read($command->scope, $command->id);
        } catch (Throwable $throwable) {
            throw new TaskStorageFailed(true, $throwable);
        }

        if (
            $retained === null || !$retained->availableAt(Task::timestamp(($this->clock)()))
            || $retained->cancellation()?->toString() !== $changed->cancellation()?->toString()
        ) {
            throw new TaskStorageFailed(true, new RuntimeException('Committed Task intent is unavailable.'));
        }

        $event = TaskChanged::fromTask($changed);
        try {
            $this->events->trigger($event);
        } catch (Throwable $throwable) {
            throw new CommittedTaskDispatchFailed($event, $throwable);
        }
    }

    /**
     * Returns current protected intent without consumption or payload disclosure
     */
    public function get(GetTaskCancellation $query): ?StrictJson
    {
        try {
            $allowed = $this->admission->allowsCancellationObservation($query->scope, $query->id, $query->caller);
        } catch (Throwable) {
            return null;
        }

        if (!$allowed) {
            return null;
        }

        $task = $this->store->read($query->scope, $query->id);
        if ($task === null || !$task->availableAt(Task::timestamp(($this->clock)()))) {
            return null;
        }

        return StrictJson::fromObject([
            'id'           => $query->id,
            'version'      => $task->version(),
            'cancellation' => $task->cancellation()
        ]);
    }
}
