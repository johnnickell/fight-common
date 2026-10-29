<?php

declare(strict_types=1);

namespace Fight\Common\Application\Task\Service;

use Closure;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Task\Exception\TaskConflict;
use Fight\Common\Application\Task\Exception\TaskRejected;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Task\Command\CreateTask;
use Fight\Common\Domain\Task\Command\RecordTaskState;
use Fight\Common\Domain\Task\Command\RemoveTasks;
use Fight\Common\Domain\Task\Event\TaskChanged;
use Fight\Common\Domain\Task\Event\TaskDataRemoved;
use Fight\Common\Domain\Task\Query\GetTask;
use Fight\Common\Domain\Task\Task;
use Fight\Common\Domain\Task\TaskDefinition;
use Fight\Common\Domain\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Throwable;

/**
 * Class TaskLifecycle
 */
final readonly class TaskLifecycle
{
    /**
     * Constructs TaskLifecycle
     *
     * @phpstan-param Closure(): int $clock Trusted server epoch-millisecond clock
     */
    public function __construct(
        private TaskStore $store,
        private TaskCreationAdmission $creation,
        private TaskUpdateAdmission $updates,
        private TaskReadAdmission $reads,
        private TaskPayloadPolicy $payloads,
        private EventDispatcher $events,
        private Closure $clock,
        private TaskRemovalAdmission $removals
    ) {
    }

    /**
     * Creates admitted durable state before acknowledgment without submitting a job
     */
    public function create(CreateTask $command): void
    {
        $changed = $this->store->commitTransactional(function () use ($command): ?Task {
            if (!$this->creation->allowsCreation($command->definition())) {
                throw new TaskRejected();
            }

            $this->validate($command->definition(), $command->state());
            $now = Task::timestamp(($this->clock)());
            // Compare original content without recalculating a retained Task's expiry at retry time.
            $comparison = $command->at(0);
            $existing = $this->store->getById($command->definition()->scope(), $command->definition()->id());
            if ($existing !== null) {
                if (!$existing->availableAt($now)) {
                    throw new TaskRejected();
                }

                if (!$existing->repeatsCreation($comparison)) {
                    throw new TaskConflict();
                }

                return null;
            }

            $candidate = $command->at($now);
            $this->store->save($candidate);

            return $candidate;
        });
        $this->dispatch($changed);
    }

    /**
     * Records current admitted producer evidence with a state-owned concurrency fence
     */
    public function record(RecordTaskState $command): void
    {
        $changed = $this->store->commitTransactional(function () use ($command): ?Task {
            if (!$this->updates->allowsUpdate($command->scope(), $command->id(), $command->producer())) {
                throw new TaskRejected();
            }

            $existing = $this->store->getById($command->scope(), $command->id());
            $now = Task::timestamp(($this->clock)());
            if (
                $existing === null || !$existing->availableAt($now)
                || $existing->definition()->producer() !== $command->producer()
            ) {
                throw new TaskRejected();
            }

            $this->validate($existing->definition(), $command->state());
            try {
                $next = $command->applyTo($existing, $now);
            } catch (DomainException) {
                throw new TaskConflict();
            }

            if ($next !== null) {
                $this->store->save($next);
            }

            return $next;
        });
        $this->dispatch($changed);
    }

    /**
     * Removes an independently admitted complete selection without inferring cancellation
     */
    public function remove(RemoveTasks $command): void
    {
        $removed = $this->store->commitTransactional(function () use ($command): ?TaskDataRemoved {
            $selection = $command->selection();
            // Check every grant before existence or deletion, including repeats and missing rows.
            foreach ($selection->ids() as $id) {
                try {
                    $allowed = $this->removals->allowsRemoval($selection->scope(), $id);
                } catch (Throwable) {
                    throw new TaskRejected();
                }

                if (!$allowed) {
                    throw new TaskRejected();
                }
            }

            $actual = $this->store->remove($selection);

            return $actual === null ? null : new TaskDataRemoved($actual);
        });
        $this->dispatch($removed);
    }

    /**
     * Returns whole safe current state or indistinguishable denied missing expired unavailability
     */
    public function get(GetTask $query): ?StrictJson
    {
        if (!$this->reads->allowsRead($query->scope(), $query->id())) {
            return null;
        }

        $task = $this->store->read($query->scope(), $query->id());
        if ($task === null || !$task->availableAt(Task::timestamp(($this->clock)()))) {
            return null;
        }

        $this->validate($task->definition(), $task->state());
        if (!$this->payloads->mayDisclose($task)) {
            return null;
        }

        return StrictJson::fromObject($task->view(Task::timestamp(($this->clock)())), 40);
    }

    /**
     * Validates original semantics while concealing private policy diagnostics
     */
    private function validate(TaskDefinition $definition, TaskState $state): void
    {
        try {
            $this->payloads->validate($definition, $state);
        } catch (Throwable) {
            throw new TaskRejected();
        }
    }

    /**
     * Dispatches reference-only facts after actual commit without a replay promise
     */
    private function dispatch(Task|TaskDataRemoved|null $task): void
    {
        if ($task === null) {
            return;
        }

        $event = $task instanceof Task ? TaskChanged::fromTask($task) : $task;
        try {
            $this->events->trigger($event);
        } catch (Throwable $throwable) {
            throw new CommittedTaskDispatchFailed($event, $throwable);
        }
    }
}
