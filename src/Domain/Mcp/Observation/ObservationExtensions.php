<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Observation;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class ObservationExtensions
 */
final readonly class ObservationExtensions extends ValueObject
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

        $snapshot = [];
        foreach ($values as $key => $value) {
            if (
                !is_string($key) || strlen($key) > 96
                || preg_match('/\A[a-z][a-z0-9.-]*\/[a-z][a-z0-9._-]*\z/D', $key) !== 1
                || (!is_string($value) && !is_int($value) && !is_bool($value) && $value !== null)
                || (is_string($value) && (strlen($value) > 256 || preg_match('//u', $value) !== 1))
            ) {
                throw new DomainException('Invalid observation extension.');
            }

            // Fresh scalar assignments detach caller-owned PHP element references.
            $snapshot[$key] = $value;
        }

        ksort($snapshot, SORT_STRING);
        if (strlen(json_encode($snapshot, JSON_THROW_ON_ERROR)) > 4096) {
            throw new DomainException('Observation extensions exceed bounds.');
        }

        return new self($snapshot);
    }

    /**
     * Creates extensions from stable structured text
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            throw new DomainException('Invalid observation extensions text.');
        }

        return self::fromArray($data);
    }

    /**
     * Returns stable structured extension text
     */
    public function toString(): string
    {
        return json_encode($this->values, JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the scalar snapshot with its existing empty-array meaning
     *
     * @return array<string, string|int|bool|null>
     */
    public function jsonSerialize(): array
    {
        return $this->values;
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
