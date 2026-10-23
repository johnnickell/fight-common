<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Read;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Value\ObservationFields;

/**
 * Class ExecutionSelection
 */
final readonly class ExecutionSelection
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
            $validated[] = ObservationFields::reference($project);
        }

        $validated = array_values(array_unique($validated));
        sort($validated, SORT_STRING);
        $safeFilters = [];
        foreach ($filters as $field => $value) {
            if (!in_array($field, ['session_id', 'consumer_reference'], true)) {
                throw new DomainException('Unsupported execution filter.');
            }

            $safeFilters[$field] = ObservationFields::reference($value);
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
        ObservationFields::structure($data, ['projects', 'filters']);
        if (!is_array($data['projects']) || !is_array($data['filters'])) {
            throw new DomainException('Invalid execution selection.');
        }

        return self::create($data['projects'], $data['filters']);
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
