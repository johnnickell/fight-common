<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Task;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Identity\Identifier;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class TaskId
 *
 * Identifies a Task only together with its separately supplied trusted scope
 */
final readonly class TaskId extends ValueObject implements Identifier
{
    /**
     * Constructs TaskId
     */
    private function __construct(private string $value)
    {
    }

    /**
     * Creates an unguessable identity without granting authority
     */
    public static function generate(): self
    {
        return self::fromString(bin2hex(random_bytes(16)));
    }

    /**
     * @inheritDoc
     */
    public static function fromString(string $value): static
    {
        if (preg_match('/\A[0-9a-f]{32}\z/D', $value) !== 1) {
            throw new DomainException('Invalid Task identity.');
        }

        return new self($value);
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Returns bytewise identity ordering without comparing scope or authority
     */
    public function compareTo(mixed $other): int
    {
        if (!$other instanceof self) {
            throw new DomainException('Task identity comparison requires a TaskId.');
        }

        return strcmp($this->value, $other->value) <=> 0;
    }
}
