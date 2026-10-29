<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Task;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class TaskSelection
 */
final readonly class TaskSelection
{
    public const int MAX_COUNT = 100;

    /**
     * Constructs TaskSelection
     *
     * @phpstan-param list<string> $ids
     */
    private function __construct(private string $scope, private array $ids)
    {
    }

    /**
     * Creates a finite explicit selection without silently dropping duplicate identities
     *
     * @phpstan-param array<mixed> $ids
     */
    public static function fromIds(string $scope, array $ids): self
    {
        TaskDefinition::reference($scope);
        if (!array_is_list($ids) || count($ids) < 1 || count($ids) > self::MAX_COUNT) {
            throw new DomainException('Invalid Task selection.');
        }

        foreach ($ids as $id) {
            if (!is_string($id)) {
                throw new DomainException('Invalid Task selection.');
            }

            TaskDefinition::identity($id);
        }

        if (count(array_unique($ids)) !== count($ids)) {
            throw new DomainException('Invalid Task selection.');
        }

        return new self($scope, $ids);
    }

    /**
     * Returns the exact consumer scope
     */
    public function scope(): string
    {
        return $this->scope;
    }

    /**
     * Returns the bounded distinct identities in supplied order
     *
     * @return list<string>
     */
    public function ids(): array
    {
        return $this->ids;
    }
}
