<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Task\Query;

use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Task\TaskDefinition;

/**
 * Class GetTask
 */
final readonly class GetTask implements Query
{
    /**
     * Constructs GetTask
     */
    public function __construct(private string $scope, private string $id)
    {
        TaskDefinition::reference($scope);
        TaskDefinition::identity($id);
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self($data['scope'], $data['id']);
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
    public function id(): string
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['scope' => $this->scope, 'id' => $this->id];
    }
}
