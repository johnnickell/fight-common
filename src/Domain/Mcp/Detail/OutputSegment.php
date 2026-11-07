<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Detail;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class OutputSegment
 */
final readonly class OutputSegment extends ValueObject
{
    /**
     * Constructs OutputSegment
     */
    private function __construct(private string $stream, private int $index, private int $offset)
    {
    }

    /**
     * Creates bounded consumer-supplied segment coordinates
     */
    public static function create(string $stream, int $index, int $offset): self
    {
        if (
            !in_array($stream, ['stdout', 'stderr', 'combined'], true)
            || $index < 0 || $index > 1000000 || $offset < 0 || $offset > 2147483647
        ) {
            throw new DomainException('Invalid output segment.');
        }

        return new self($stream, $index, $offset);
    }

    /**
     * Creates a segment from structured coordinates
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['index', 'offset', 'stream']) {
            throw new DomainException('Invalid output segment structure.');
        }

        if (!is_string($data['stream']) || !is_int($data['index']) || !is_int($data['offset'])) {
            throw new DomainException('Invalid output segment.');
        }

        return self::create($data['stream'], $data['index'], $data['offset']);
    }

    /**
     * Creates output coordinates from stable structured text
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            throw new DomainException('Invalid output segment text.');
        }

        return self::fromArray($data);
    }

    /**
     * Returns stable output-coordinate text
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns structured output coordinates
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Returns the selected output stream
     */
    public function stream(): string
    {
        return $this->stream;
    }

    /**
     * Returns the supplied segment index
     */
    public function index(): int
    {
        return $this->index;
    }

    /**
     * Returns the supplied byte offset without inferring continuity
     */
    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * Returns bounded coordinates
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['stream' => $this->stream, 'index' => $this->index, 'offset' => $this->offset];
    }
}
