<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Session;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Value\ObservationReference;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class ExecutionSelection
 */
final readonly class ExecutionSelection extends ValueObject
{
    /**
     * Constructs ExecutionSelection
     *
     * @phpstan-param list<string> $projects
     * @phpstan-param array<string, string> $filters
     */
    private function __construct(private array $projects, private array $filters)
    {
    }

    /**
     * Creates a finite selection without granting read authority
     *
     * @param array<mixed> $projects
     * @param array<string, mixed> $filters
     */
    public static function create(array $projects, array $filters = []): self
    {
        if (!array_is_list($projects) || count($projects) > 16 || count($filters) > 2) {
            throw new DomainException('Invalid execution selection.');
        }

        $validated = [];
        foreach ($projects as $project) {
            $validated[] = ObservationReference::fromString($project)->toString();
        }

        $validated = array_values(array_unique($validated));
        sort($validated, SORT_STRING);
        $safeFilters = [];
        foreach ($filters as $field => $value) {
            if (!in_array($field, ['session_id', 'consumer_reference'], true)) {
                throw new DomainException('Unsupported execution filter.');
            }

            $safeFilters[$field] = ObservationReference::fromString($value)->toString();
        }

        ksort($safeFilters, SORT_STRING);

        return new self($validated, $safeFilters);
    }

    /**
     * Creates a selection from its exact representation
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['filters', 'projects']) {
            throw new DomainException('Invalid execution selection structure.');
        }

        if (!is_array($data['projects']) || !is_array($data['filters'])) {
            throw new DomainException('Invalid execution selection.');
        }

        return self::create($data['projects'], $data['filters']);
    }

    /**
     * Creates a selection from stable structured text without granting access
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            throw new DomainException('Invalid execution selection text.');
        }

        return self::fromArray($data);
    }

    /**
     * Returns stable selection text without grants
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the structured selection rather than an encoded string
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Returns the selected project references
     *
     * @return list<string>
     */
    public function projects(): array
    {
        return $this->projects;
    }

    /**
     * Returns the validated exact-match filters
     *
     * @return array<string, string>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * Returns the serializable selection without authority
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['projects' => $this->projects, 'filters' => $this->filters];
    }
}
