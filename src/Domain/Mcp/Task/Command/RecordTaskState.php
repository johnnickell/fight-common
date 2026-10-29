<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Task\Command;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskDefinition;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Messaging\Command\Command;

/**
 * Class RecordTaskState
 */
final readonly class RecordTaskState implements Command
{
    /**
     * Constructs RecordTaskState
     */
    public function __construct(
        private string $scope,
        private string $id,
        private string $producer,
        private int $expectedVersion,
        private TaskState $state,
        private ?int $pollInterval = null
    ) {
        TaskDefinition::reference($scope);
        TaskDefinition::identity($id);
        TaskDefinition::reference($producer);
        Task::interval($pollInterval);
        if ($expectedVersion < 1 || $expectedVersion > Task::MAX_VERSION) {
            throw new DomainException('Invalid Task expected version.');
        }
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self(
            $data['scope'],
            $data['id'],
            $data['producer'],
            $data['expected_version'],
            TaskState::fromArray($data['state']),
            $data['poll_interval']
        );
    }

    /**
     * Returns the exact scope
     */
    public function scope(): string
    {
        return $this->scope;
    }

    /**
     * Returns the exact Task identity
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Returns the trusted producer reference without granting permission
     */
    public function producer(): string
    {
        return $this->producer;
    }

    /**
     * Returns proposed state for original-contract validation
     */
    public function state(): TaskState
    {
        return $this->state;
    }

    /**
     * Applies the state-owned transition decision
     */
    public function applyTo(Task $task, int $now): ?Task
    {
        return $task->transition($this->producer, $this->expectedVersion, $this->state, $this->pollInterval, $now);
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'scope'            => $this->scope,
            'id'               => $this->id,
            'producer'         => $this->producer,
            'expected_version' => $this->expectedVersion,
            'state'            => $this->state->toArray(),
            'poll_interval'    => $this->pollInterval
        ];
    }
}
