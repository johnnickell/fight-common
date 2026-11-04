<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Task;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Common\Domain\Value\ValueObject;
use TypeError;

/**
 * Class TaskSelection
 */
final readonly class TaskSelection extends ValueObject
{
    public const int MAX_COUNT = 100;

    /**
     * Constructs TaskSelection
     *
     * @phpstan-param list<TaskId> $ids
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
            if (!$id instanceof TaskId) {
                throw new DomainException('Invalid Task selection.');
            }
        }

        if (count(array_unique($ids)) !== count($ids)) {
            throw new DomainException('Invalid Task selection.');
        }

        return new self($scope, $ids);
    }

    /**
     * Creates a selection from explicit message bytes
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (
            count($data) !== 2 || array_diff(['scope', 'ids'], array_keys($data)) !== []
            || !is_array($data['ids']) || !array_is_list($data['ids'])
        ) {
            throw new DomainException('Invalid Task selection representation.');
        }

        $ids = [];
        foreach ($data['ids'] as $id) {
            if (!is_string($id)) {
                throw new DomainException('Invalid Task selection identity.');
            }

            $ids[] = TaskId::fromString($id);
        }

        return self::fromIds($data['scope'], $ids);
    }

    /**
     * Creates an ordered selection from structured JSON
     */
    public static function fromString(string $value): static
    {
        try {
            return self::fromArray(StrictJson::fromString($value, 2)->properties());
        } catch (TypeError $typeError) {
            throw new DomainException('Invalid Task selection representation.', 0, $typeError);
        }
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
     * @return list<TaskId>
     */
    public function ids(): array
    {
        return $this->ids;
    }

    /**
     * Returns explicit identity bytes in supplied order for messages
     *
     * @return array{scope: string, ids: list<string>}
     */
    public function toArray(): array
    {
        return [
            'scope' => $this->scope,
            'ids'   => array_map(static fn(TaskId $id): string => $id->toString(), $this->ids)
        ];
    }

    /**
     * Returns exact scope and ordered identities without implying authority
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns a structured selection rather than an embedded JSON string
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
