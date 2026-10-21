<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class ObservationExtensions
 */
final readonly class ObservationExtensions
{
    /**
     * Constructs ObservationExtensions
     *
     * @param array<string, string|integer|boolean|null> $values
     */
    private function __construct(private array $values)
    {
    }

    /**
     * Creates bounded namespaced scalar extensions
     *
     * @param array<array-key, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        if (count($values) > 32) {
            throw new DomainException('Observation extensions exceed bounds.');
        }

        foreach ($values as $key => $value) {
            if (
                !is_string($key) || strlen($key) > 96
                || preg_match('/\A[a-z][a-z0-9.-]*\/[a-z][a-z0-9._-]*\z/D', $key) !== 1
                || (!is_string($value) && !is_int($value) && !is_bool($value) && $value !== null)
                || (is_string($value) && (strlen($value) > 256 || preg_match('//u', $value) !== 1))
            ) {
                throw new DomainException('Invalid observation extension.');
            }
        }

        ksort($values, SORT_STRING);
        if (strlen(json_encode($values, JSON_THROW_ON_ERROR)) > 4096) {
            throw new DomainException('Observation extensions exceed bounds.');
        }

        return new self($values);
    }

    /**
     * Returns the immutable scalar snapshot
     *
     * @return array<string, string|int|bool|null>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
