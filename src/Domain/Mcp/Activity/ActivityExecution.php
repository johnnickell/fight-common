<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Activity;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class ActivityExecution
 *
 * Cohesive optional operation evidence within phase activity
 */
final readonly class ActivityExecution extends ValueObject
{
    /**
     * Constructs ActivityExecution
     */
    private function __construct(
        private ?ActivityTarget $target,
        private ActivityTiming $timing,
        private ActivityResult $result
    ) {
    }

    /**
     * Creates execution evidence without inferring any missing facts
     */
    public static function fromParts(?ActivityTarget $target, ActivityTiming $timing, ActivityResult $result): self
    {
        return new self($target, $timing, $result);
    }

    /**
     * Creates execution evidence from its exact closed representation
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $keys = array_keys($data);
        sort($keys);
        if (
            $keys !== ['result', 'target', 'timing']
            || ($data['target'] !== null && !is_array($data['target']))
            || !is_array($data['timing']) || !is_array($data['result'])
        ) {
            throw new DomainException('Invalid activity execution structure.');
        }

        return self::fromParts(
            $data['target'] === null ? null : ActivityTarget::fromArray($data['target']),
            ActivityTiming::fromArray($data['timing']),
            ActivityResult::fromArray($data['result'])
        );
    }

    /**
     * Creates execution evidence from structured text
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            throw new DomainException('Invalid activity execution text.');
        }

        return self::fromArray($data);
    }

    /**
     * Returns the safe target or an explicitly unknown target
     */
    public function target(): ?ActivityTarget
    {
        return $this->target;
    }

    /**
     * Returns known timing without deriving missing measurements
     */
    public function timing(): ActivityTiming
    {
        return $this->timing;
    }

    /**
     * Returns the observed boundary result without business inference
     */
    public function result(): ActivityResult
    {
        return $this->result;
    }

    /**
     * Returns stable structured execution text
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the closed execution representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'target' => $this->target?->toArray(),
            'timing' => $this->timing->toArray(),
            'result' => $this->result->toArray()
        ];
    }

    /**
     * Returns deliberate structured JSON
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
