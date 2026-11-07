<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Task\Command;

use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskDefinition;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Messaging\Command\Command;

/**
 * Class CreateTask
 */
final readonly class CreateTask implements Command
{
    /**
     * Constructs CreateTask
     */
    public function __construct(
        private TaskDefinition $definition,
        private TaskState $state,
        private ?int $ttl = null,
        private ?int $pollInterval = null
    ) {
        Task::interval($ttl);
        Task::interval($pollInterval);
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self(
            TaskDefinition::fromArray($data['definition']),
            TaskState::fromArray($data['state']),
            $data['ttl'],
            $data['poll_interval']
        );
    }

    /**
     * Returns the original immutable operation context
     */
    public function definition(): TaskDefinition
    {
        return $this->definition;
    }

    /**
     * Returns the submitted initial state
     */
    public function state(): TaskState
    {
        return $this->state;
    }

    /**
     * Creates the initial lifecycle at the supplied server time
     */
    public function at(int $now): Task
    {
        return Task::create($this->definition, $this->state, $this->ttl, $this->pollInterval, $now);
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'definition'    => $this->definition->toArray(),
            'state'         => $this->state->toArray(),
            'ttl'           => $this->ttl,
            'poll_interval' => $this->pollInterval
        ];
    }
}
