<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Task\Event;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskDefinition;
use Fight\Common\Domain\Messaging\Event\Event;

/**
 * Class TaskChanged
 */
final readonly class TaskChanged implements Event
{
    /**
     * Constructs TaskChanged
     */
    private function __construct(private string $scope, private string $id, private int $version)
    {
        TaskDefinition::reference($scope);
        TaskDefinition::identity($id);
        if ($version < 1 || $version > Task::MAX_VERSION) {
            throw new DomainException('Invalid Task fact version.');
        }
    }

    /**
     * Creates a reference-only committed fact without status or protected payloads
     */
    public static function fromTask(Task $task): self
    {
        return new self($task->definition()->scope(), $task->definition()->id(), $task->version());
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self($data['scope'], $data['id'], $data['version']);
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['scope' => $this->scope, 'id' => $this->id, 'version' => $this->version];
    }
}
