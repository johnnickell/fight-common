<?php

declare(strict_types=1);

namespace Fight\Common\Domain\EventSourcing;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Identity\Identifier;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class StreamId
 *
 * Stable identity of one event stream
 */
final readonly class StreamId extends ValueObject implements Identifier
{
    /**
     * Constructs StreamId
     */
    public function __construct(
        private string $aggregateName,
        private string $identifier
    ) {
        if ('' === $aggregateName) {
            throw new DomainException('Aggregate name cannot be empty.');
        }

        if ('' === $identifier) {
            throw new DomainException('Aggregate identifier cannot be empty.');
        }
    }

    /**
     * Creates a stream identity from its canonical versioned Base64 frame
     *
     * @throws DomainException When the frame or either component is invalid
     */
    public static function fromString(string $value): static
    {
        $parts = explode(':', $value);

        if (count($parts) !== 4 || $parts[0] !== 'stream' || $parts[1] !== 'v1') {
            throw new DomainException('Invalid stream identity representation.');
        }

        return new static(self::decodeComponent($parts[2]), self::decodeComponent($parts[3]));
    }

    /**
     * Returns the stable aggregate name
     */
    public function aggregateName(): string
    {
        return $this->aggregateName;
    }

    /**
     * Returns the aggregate identifier
     */
    public function identifier(): string
    {
        return $this->identifier;
    }

    /**
     * Returns the canonical ASCII representation of both component byte strings
     */
    public function toString(): string
    {
        return 'stream:v1:'.base64_encode($this->aggregateName).':'.base64_encode($this->identifier);
    }

    /**
     * Returns bytewise ordering by aggregate name first and identifier second
     *
     * @throws DomainException When the other value is not a StreamId
     */
    public function compareTo(mixed $other): int
    {
        if (!$other instanceof self) {
            throw new DomainException('Stream identity comparison requires a StreamId.');
        }

        $comparison = strcmp($this->aggregateName, $other->aggregateName);

        if ($comparison !== 0) {
            return $comparison <=> 0;
        }

        return strcmp($this->identifier, $other->identifier) <=> 0;
    }

    /**
     * Decodes a component only when its Base64 spelling is canonical
     */
    private static function decodeComponent(string $value): string
    {
        $decoded = base64_decode($value, true);

        if ($decoded === false || base64_encode($decoded) !== $value) {
            throw new DomainException('Invalid stream identity encoding.');
        }

        return $decoded;
    }
}
