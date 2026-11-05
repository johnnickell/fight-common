<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Task\Query;

use Fight\Common\Domain\Mcp\Task\TaskDefinition;
use Fight\Common\Domain\Mcp\Task\TaskId;
use Fight\Common\Domain\Messaging\Query\Query;

/**
 * Class GetTask
 */
final readonly class GetTask implements Query
{
    /**
     * Constructs GetTask
     */
    public function __construct(private string $scope, private TaskId $id)
    {
        TaskDefinition::reference($scope);
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self($data['scope'], TaskId::fromString($data['id']));
    }

    /**
     * Returns the selected scope
     */
    public function scope(): string
    {
        return $this->scope;
    }

    /**
     * Returns the selected identity without granting access
     */
    public function id(): TaskId
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['scope' => $this->scope, 'id' => $this->id->toString()];
    }
}
