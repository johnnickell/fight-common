<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Task;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Common\Domain\Value\ValueObject;
use TypeError;

/**
 * Class TaskDefinition
 */
final readonly class TaskDefinition extends ValueObject
{
    /**
     * Constructs TaskDefinition
     */
    private function __construct(
        private TaskId $id,
        private string $scope,
        private string $producer,
        private StrictJson $operation
    ) {
    }

    /**
     * Creates immutable original operation context supplied by trusted composition
     */
    public static function create(TaskId $id, string $scope, string $producer, StrictJson $operation): self
    {
        self::reference($scope);
        self::reference($producer);
        $operation = StrictJson::fromData($operation, 64);
        if (!$operation->isObject() || strlen($operation->toString()) > 8192) {
            throw new DomainException('Invalid Task operation context.');
        }

        return new self($id, $scope, $producer, $operation);
    }

    /**
     * Creates the version-one definition without interpreting a current operation catalog
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (count($data) !== 4 || array_diff(['id', 'scope', 'producer', 'operation'], array_keys($data)) !== []) {
            throw new DomainException('Invalid Task definition representation.');
        }

        return self::create(
            TaskId::fromString($data['id']),
            $data['scope'],
            $data['producer'],
            StrictJson::fromData($data['operation'])
        );
    }

    /**
     * Creates the original context from its structured JSON representation
     */
    public static function fromString(string $value): static
    {
        try {
            return self::fromArray(StrictJson::fromString($value, 65)->properties());
        } catch (TypeError $typeError) {
            throw new DomainException('Invalid Task definition representation.', 0, $typeError);
        }
    }

    /**
     * Validates bounded case-sensitive consumer references
     */
    public static function reference(string $value): string
    {
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:\/-]{0,95}\z/D', $value) !== 1) {
            throw new DomainException('Invalid Task reference.');
        }

        return $value;
    }

    /**
     * Returns the exact identity
     */
    public function id(): TaskId
    {
        return $this->id;
    }

    /**
     * Returns the trusted scope reference
     */
    public function scope(): string
    {
        return $this->scope;
    }

    /**
     * Returns the bound producer reference
     */
    public function producer(): string
    {
        return $this->producer;
    }

    /**
     * Returns the retained original contract and operation context
     */
    public function operation(): StrictJson
    {
        return $this->operation;
    }

    /**
     * Returns protected persistence data rather than a public projection
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'        => $this->id->toString(),
            'scope'     => $this->scope,
            'producer'  => $this->producer,
            'operation' => $this->operation
        ];
    }

    /**
     * Returns exact original context including source JSON ordering and numeric kinds
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Returns structured protected context rather than an embedded JSON string
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
