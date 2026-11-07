<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Task\Command;

use Fight\Common\Domain\Mcp\Task\TaskDefinition;
use Fight\Common\Domain\Mcp\Task\TaskId;
use Fight\Common\Domain\Messaging\Command\Command;

/**
 * Class RequestTaskCancellation
 */
final readonly class RequestTaskCancellation implements Command
{
    /**
     * Constructs RequestTaskCancellation
     */
    public function __construct(public string $scope, public TaskId $id, public string $caller)
    {
        TaskDefinition::reference($scope);
        TaskDefinition::reference($caller);
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self($data['scope'], TaskId::fromString($data['id']), $data['caller']);
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['scope' => $this->scope, 'id' => $this->id->toString(), 'caller' => $this->caller];
    }
}
