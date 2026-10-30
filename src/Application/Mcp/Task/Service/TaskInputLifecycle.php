<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\Service;

use Closure;
use Fight\Common\Application\Mcp\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Command\AcquireTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\RetireTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\SupplyTaskInput;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskInput;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Throwable;

/**
 * Class TaskInputLifecycle
 */
final readonly class TaskInputLifecycle
{
    /**
     * Constructs TaskInputLifecycle
     *
     * @phpstan-param Closure(): int $clock Trusted server epoch-millisecond clock
     */
    public function __construct(
        private TaskStore $store,
        private TaskUpdateAdmission $producers,
        private TaskInputAdmission $admission,
        private TaskPayloadPolicy $payloads,
        private EventDispatcher $events,
        private Closure $clock,
        private TaskCancellationAdmission $cancellations
    ) {
    }

    /**
     * Issues protected contracts atomically with all outstanding public requests
     */
    public function request(RequestTaskInput $command): void
    {
        $this->mutate(function () use ($command): Task {
            $this->producer($command->scope, $command->id, $command->producer);
            $task = $this->load($command->scope, $command->id);
            if ($task->definition()->producer() !== $command->producer) {
                throw new TaskRejected();
            }

            $arguments = $task->definition()->operation()->get('arguments');
            if (
                $command->batch->data()->get('confirmation')
                && (!$arguments instanceof StrictJson || !$arguments->isObject())
            ) {
                throw new TaskRejected();
            }

            try {
                TaskInputForms::validate($command->batch);
            } catch (Throwable) {
                throw new TaskRejected();
            }

            return $task->requestInput($command->batch, $command->expectedVersion, $this->now());
        });
    }

    /**
     * Records all validated active responses before acknowledging a partial or ignored batch
     */
    public function supply(SupplyTaskInput $command): void
    {
        $this->mutate(function () use ($command): ?Task {
            $this->permit(fn(): bool => $this->admission->allowsSubmission(
                $command->scope,
                $command->id,
                $command->caller
            ));
            $task = $this->load($command->scope, $command->id);
            // Known authorized terminal Tasks acknowledge safely without validating or applying inactive content.
            if ($task->state()->isTerminal()) {
                return null;
            }

            try {
                $answers = TaskInputForms::responses(
                    $command->responses,
                    $task->inputs()->active($command->caller, $this->now())
                );
            } catch (Throwable) {
                throw new TaskRejected();
            }

            if ($answers->properties() === []) {
                return null;
            }

            return $task->answerInput($answers, $command->caller, $this->now());
        });
    }

    /**
     * Acquires one ready group under fresh business authority without executing consumer work
     */
    public function acquire(AcquireTaskInput $command): void
    {
        $this->mutate(function () use ($command): Task {
            $this->permit(fn(): bool => $this->admission->allowsContinuation(
                $command->scope,
                $command->id,
                $command->caller,
                $command->group
            ));
            $this->permit(fn(): bool => $this->cancellations->allowsCancellationObservation(
                $command->scope,
                $command->id,
                $command->caller
            ));
            $task = $this->load($command->scope, $command->id);

            return $task->acquireInput($command->group, $command->caller, $this->now());
        });
    }

    /**
     * Removes superseded payloads without allowing their keys to identify later requests
     */
    public function retire(RetireTaskInput $command): void
    {
        $this->mutate(function () use ($command): Task {
            $this->producer($command->scope, $command->id, $command->producer);
            $task = $this->load($command->scope, $command->id);
            if ($task->definition()->producer() !== $command->producer) {
                throw new TaskRejected();
            }

            return $task->retireInput($command->group, $command->expectedVersion, $this->now());
        });
    }

    /**
     * Returns separately protected durable input observations without mutating or consuming them
     */
    public function get(GetTaskInput $query): ?StrictJson
    {
        try {
            $allowed = $this->admission->allowsObservation($query->scope, $query->id, $query->caller)
                && $this->cancellations->allowsCancellationObservation($query->scope, $query->id, $query->caller);
        } catch (Throwable) {
            return null;
        }

        if (!$allowed) {
            return null;
        }

        $task = $this->store->read($query->scope, $query->id);
        if ($task === null || !$task->availableAt($this->now())) {
            return null;
        }

        return StrictJson::fromObject([
            'definition'   => $task->definition()->toArray(),
            'terminal'     => $task->state()->isTerminal(),
            'version'      => $task->version(),
            'cancellation' => $task->cancellation(),
            'groups'       => $task->inputs()->observe($query->caller, $this->now(), $task->state()->isTerminal())
        ], 72);
    }

    /**
     * Validates current producer authority before any protected lookup
     */
    private function producer(string $scope, string $id, string $producer): void
    {
        $this->permit(fn(): bool => $this->producers->allowsUpdate($scope, $id, $producer));
    }

    /**
     * Validates one independent grant without disclosing policy diagnostics
     *
     * @phpstan-param Closure(): bool $decision Current authority decision
     */
    private function permit(Closure $decision): void
    {
        try {
            $allowed = $decision();
        } catch (Throwable) {
            throw new TaskRejected();
        }

        if (!$allowed) {
            throw new TaskRejected();
        }
    }

    /**
     * Returns one currently retained protected Task inside the owned transaction
     */
    private function load(string $scope, string $id): Task
    {
        $task = $this->store->getById($scope, $id);
        if ($task === null || !$task->availableAt($this->now())) {
            throw new TaskRejected();
        }

        return $task;
    }

    /**
     * Returns the validated current server timestamp
     */
    private function now(): int
    {
        return Task::timestamp(($this->clock)());
    }

    /**
     * Records one input mutation under the existing lifecycle lock before safe fact delivery
     *
     * @phpstan-param Closure(): ?Task $operation Validated state transition
     */
    private function mutate(Closure $operation): void
    {
        $task = $this->store->commitTransactional(function () use ($operation): ?Task {
            try {
                $task = $operation();
            } catch (DomainException) {
                throw new TaskConflict();
            }

            if ($task !== null) {
                try {
                    $this->payloads->validate($task->definition(), $task->state());
                } catch (Throwable) {
                    throw new TaskRejected();
                }

                $this->store->save($task);
            }

            return $task;
        });
        if ($task === null) {
            return;
        }

        $event = TaskChanged::fromTask($task);
        try {
            $this->events->trigger($event);
        } catch (Throwable $throwable) {
            throw new CommittedTaskDispatchFailed($event, $throwable);
        }
    }
}
