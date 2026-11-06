<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Activity;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Value\ObservationReference;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class ActivityTarget
 *
 * Safe operation classification, never arguments, a path or a command line
 */
final readonly class ActivityTarget extends ValueObject
{
    /**
     * Constructs ActivityTarget
     */
    private function __construct(private string $capability, private string $name)
    {
    }

    /**
     * Creates a bounded safe operation target without normalization
     */
    public static function fromStrings(string $capability, string $name): self
    {
        return new self(
            ObservationReference::fromString($capability)->toString(),
            ObservationReference::fromString($name)->toString()
        );
    }

    /**
     * Creates a target from its exact structured representation
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['capability', 'name'] || !is_string($data['capability']) || !is_string($data['name'])) {
            throw new DomainException('Invalid activity target.');
        }

        return self::fromStrings($data['capability'], $data['name']);
    }

    /**
     * Creates a target from structured text
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            throw new DomainException('Invalid activity target text.');
        }

        return self::fromArray($data);
    }

    /**
     * Returns the safe capability classification
     */
    public function capability(): string
    {
        return $this->capability;
    }

    /**
     * Returns the safe operation name
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns stable structured target text
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the closed target representation
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['capability' => $this->capability, 'name' => $this->name];
    }

    /**
     * Returns deliberate structured JSON
     *
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
