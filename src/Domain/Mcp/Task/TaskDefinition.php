<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Task;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class TaskDefinition
 */
final readonly class TaskDefinition
{
    /**
     * Constructs TaskDefinition
     */
    private function __construct(
        private string $id,
        private string $scope,
        private string $producer,
        private StrictJson $operation
    ) {
    }

    /**
     * Creates an unguessable application identity without granting authority
     */
    public static function newId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Creates immutable original operation context supplied by trusted composition
     */
    public static function create(string $id, string $scope, string $producer, StrictJson $operation): self
    {
        self::identity($id);
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

        return self::create($data['id'], $data['scope'], $data['producer'], StrictJson::fromData($data['operation']));
    }

    /**
     * Validates exact application-generated identities
     */
    public static function identity(string $id): string
    {
        if (preg_match('/\A[0-9a-f]{32}\z/D', $id) !== 1) {
            throw new DomainException('Invalid Task identity.');
        }

        return $id;
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
    public function id(): string
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
            'id'        => $this->id,
            'scope'     => $this->scope,
            'producer'  => $this->producer,
            'operation' => $this->operation
        ];
    }
}
