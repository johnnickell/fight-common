<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Task\Query;

use Fight\Common\Domain\Mcp\Task\TaskDefinition;
use Fight\Common\Domain\Messaging\Query\Query;

/**
 * Class GetTaskCancellation
 */
final readonly class GetTaskCancellation implements Query
{
    /**
     * Constructs GetTaskCancellation
     */
    public function __construct(public string $scope, public string $id, public string $caller)
    {
        TaskDefinition::reference($scope);
        TaskDefinition::identity($id);
        TaskDefinition::reference($caller);
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self($data['scope'], $data['id'], $data['caller']);
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['scope' => $this->scope, 'id' => $this->id, 'caller' => $this->caller];
    }
}
