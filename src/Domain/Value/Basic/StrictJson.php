<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\Basic;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\ValueObject;
use JsonException;
use Override;
use stdClass;

/**
 * Class StrictJson
 *
 * Objects are immutable StrictJson nodes; lists are arrays of nodes or scalars.
 * Generic PHP objects are accepted only at construction and emitted only for JSON serialization.
 */
final readonly class StrictJson extends ValueObject
{
    /**
     * Constructs StrictJson
     *
     * @phpstan-param array<array-key, mixed>|string|int|float|bool|null $data
     */
    private function __construct(
        private array|string|int|float|bool|null $data,
        private bool $object
    ) {
    }

    /**
     * Creates a bounded snapshot of plain JSON data without executing consumer serializers
     */
    public static function fromData(mixed $data, int $maxDepth = 64): static
    {
        if ($maxDepth < 0 || $maxDepth > 511) {
            throw new DomainException('The JSON nesting limit must be between 0 and 511.');
        }

        $value = self::normalize($data, 0, $maxDepth);
        $result = $value instanceof self ? $value : new self($value, false);
        // Encoding validates Unicode and finite numbers after rejecting arbitrary objects.
        $result->toString();

        return $result;
    }

    /**
     * Creates a JSON object even when its properties are empty or numerically named
     *
     * @param array<string|int, mixed> $properties
     * @param integer                  $maxDepth
     */
    public static function fromObject(array $properties = [], int $maxDepth = 64): self
    {
        return self::fromData((object) $properties, $maxDepth);
    }

    /**
     * @inheritDoc
     */
    public static function fromString(string $value, int $maxDepth = 64): static
    {
        try {
            $data = json_decode($value, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new DomainException('Invalid JSON text.', 0, $jsonException);
        }

        return self::fromData($data, $maxDepth);
    }

    /**
     * Returns whether this value is a JSON object rather than a list or scalar
     */
    public function isObject(): bool
    {
        return $this->object;
    }

    /**
     * Returns plain scalar or list data, retaining JSON objects as immutable typed nodes
     *
     * @phpstan-return self|list<mixed>|string|int|float|bool|null
     */
    public function toData(): self|array|string|int|float|bool|null
    {
        return $this->object ? $this : $this->data;
    }

    /**
     * Returns object properties without exposing mutable generic objects
     *
     * @return array<string|int, mixed>
     */
    public function properties(): array
    {
        if (!$this->object) {
            throw new DomainException('JSON properties require an object.');
        }

        return $this->data;
    }

    /**
     * Returns a named object property or null when absent
     *
     * @phpstan-return self|list<mixed>|string|int|float|bool|null
     */
    public function get(string $name): self|array|string|int|float|bool|null
    {
        return $this->properties()[$name] ?? null;
    }

    /**
     * Returns whether an object property exists including an explicit null
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->properties());
    }

    /**
     * Returns a new object with one replaced or added property
     */
    public function with(string $name, mixed $value, int $maxDepth = 64): self
    {
        $properties = $this->properties();
        $properties[$name] = $value;

        return self::fromObject($properties, $maxDepth);
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        try {
            return json_encode($this, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $jsonException) {
            throw new DomainException('JSON data must contain valid Unicode and finite numbers.', 0, $jsonException);
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->object ? (object) $this->data : $this->data;
    }

    /**
     * Creates isolated object nodes and lists within the caller's nesting limit
     *
     * @phpstan-return self|list<mixed>|string|int|float|bool|null
     */
    private static function normalize(mixed $value, int $depth, int $maxDepth): self|array|string|int|float|bool|null
    {
        if ($depth > $maxDepth) {
            throw new DomainException('JSON data exceeds the supported nesting depth.');
        }

        $object = false;
        if ($value instanceof self) {
            $object = $value->object;
            $value = $value->data;
        } elseif ($value instanceof stdClass && $value::class === stdClass::class) {
            $object = true;
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            $object = $object || !array_is_list($value);
            $items = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && str_starts_with($key, "\0")) {
                    throw new DomainException('JSON object keys must not begin with U+0000.');
                }

                $items[$key] = self::normalize($item, $depth + 1, $maxDepth);
            }

            return $object ? new self($items, true) : $items;
        }

        if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        throw new DomainException('JSON data must contain only plain JSON values.');
    }
}
