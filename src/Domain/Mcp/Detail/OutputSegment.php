<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Detail;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Value\ObservationFields;

/**
 * Class OutputSegment
 */
final readonly class OutputSegment
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
        ObservationFields::structure($data, ['stream', 'index', 'offset']);
        if (!is_string($data['stream']) || !is_int($data['index']) || !is_int($data['offset'])) {
            throw new DomainException('Invalid output segment.');
        }

        return self::create($data['stream'], $data['index'], $data['offset']);
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
