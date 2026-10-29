<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Notice;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class NoticeSelection
 */
final readonly class NoticeSelection
{
    private const array REFERENCES = [
        'id', 'producer', 'topic', 'type', 'subject', 'recipient', 'author', 'command', 'event', 'action'
    ];
    private const array TIMES = ['recorded_from', 'recorded_until', 'occurred_from', 'occurred_until'];

    /**
     * Constructs NoticeSelection
     *
     * @phpstan-param list<string> $scopes
     * @phpstan-param array<string, string> $filters
     */
    private function __construct(private array $scopes, private array $filters)
    {
    }

    /**
     * Creates a bounded exact selection without authority
     *
     * @param array<mixed> $scopes
     * @param array<array-key, mixed> $filters
     */
    public static function create(array $scopes, array $filters = []): self
    {
        if (!array_is_list($scopes) || count($scopes) > 16 || count($filters) > 14) {
            throw new DomainException('Invalid notice selection.');
        }

        $safeScopes = [];
        foreach ($scopes as $scope) {
            $safeScopes[] = Notice::reference($scope);
        }

        $safeScopes = array_values(array_unique($safeScopes));
        sort($safeScopes, SORT_STRING);
        $safeFilters = [];
        foreach ($filters as $field => $value) {
            if (in_array($field, self::REFERENCES, true)) {
                $safeFilters[$field] = Notice::reference($value);
            } elseif (in_array($field, self::TIMES, true) && is_string($value)) {
                $safeFilters[$field] = self::instant($value);
            } else {
                throw new DomainException('Unsupported notice filter.');
            }
        }

        foreach (['recorded', 'occurred'] as $time) {
            if (
                isset($safeFilters[$time.'_from'], $safeFilters[$time.'_until'])
                && $safeFilters[$time.'_from'] > $safeFilters[$time.'_until']
            ) {
                throw new DomainException('Reversed notice time range.');
            }
        }

        ksort($safeFilters, SORT_STRING);

        return new self($safeScopes, $safeFilters);
    }

    /**
     * Creates a selection from its exact representation
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        self::structure($data, ['scopes', 'filters']);
        if (!is_array($data['scopes']) || !is_array($data['filters'])) {
            throw new DomainException('Invalid notice selection.');
        }

        return self::create($data['scopes'], $data['filters']);
    }

    /**
     * Validates an exact finite payload structure
     *
     * @phpstan-param array<array-key, mixed> $data
     * @phpstan-param list<string> $fields
     */
    public static function structure(array $data, array $fields): void
    {
        $keys = array_keys($data);
        sort($keys);
        sort($fields);
        if ($keys !== $fields) {
            throw new DomainException('Invalid notice query structure.');
        }
    }

    /**
     * Returns canonical UTC microseconds for comparison without changing source precision
     */
    public static function instant(string $value): string
    {
        $value = NoticeOccurrence::fromString($value)->toString();
        $fraction = strlen($value) === 20 ? '' : substr($value, 20, -1);

        return substr($value, 0, 19).'.'.str_pad($fraction, 6, '0').'Z';
    }

    /**
     * Returns finite selected scopes
     *
     * @return list<string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /**
     * Returns validated exact filters
     *
     * @return array<string, string>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * Returns whether complete notice metadata matches all selection constraints
     */
    public function matches(Notice $notice, string $recordedAt): bool
    {
        if (!in_array($notice->scope(), $this->scopes, true)) {
            return false;
        }

        $data = $notice->toArray();
        $relations = $notice->relationships()->toArray();
        foreach ($this->filters as $field => $value) {
            if (in_array($field, self::TIMES, true)) {
                $time = str_starts_with($field, 'recorded_') ? $recordedAt : $notice->occurrence()?->toString();
                if ($time === null) {
                    return false;
                }

                $time = self::instant($time);
                if (str_ends_with($field, '_from') ? $time < $value : $time >= $value) {
                    return false;
                }
            } elseif ($field === 'recipient') {
                if (!in_array($value, $relations['recipients'] ?? [], true)) {
                    return false;
                }
            } elseif (($data[$field] ?? $relations[$field] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the serializable selection without access grants
     *
     * @return array{scopes: list<string>, filters: array<string, string>}
     */
    public function toArray(): array
    {
        return ['scopes' => $this->scopes, 'filters' => $this->filters];
    }
}
