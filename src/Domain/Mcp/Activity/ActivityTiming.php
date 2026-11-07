<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Activity;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Observation\ObservationOccurrence;
use Fight\Common\Domain\Value\DateTime\Duration;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class ActivityTiming
 *
 * Independent source endpoints and measured elapsed time without inference
 */
final readonly class ActivityTiming extends ValueObject
{
    /**
     * Constructs ActivityTiming
     */
    private function __construct(
        private ?ObservationOccurrence $startedAt,
        private ?ObservationOccurrence $endedAt,
        private ?Duration $elapsed
    ) {
    }

    /**
     * Creates timing while preserving partial endpoints and source precision
     */
    public static function fromParts(
        ?ObservationOccurrence $startedAt,
        ?ObservationOccurrence $endedAt,
        ?Duration $elapsed
    ): self {
        if (
            ($startedAt !== null && $endedAt !== null && $endedAt->instant() < $startedAt->instant())
            || ($elapsed !== null && $elapsed->toMicroseconds() < 0)
        ) {
            throw new DomainException('Invalid activity timing.');
        }

        return new self($startedAt, $endedAt, $elapsed);
    }

    /**
     * Creates timing from the exact closed representation
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['elapsed', 'ended_at', 'started_at']) {
            throw new DomainException('Invalid activity timing structure.');
        }

        foreach ($data as $value) {
            if ($value !== null && !is_string($value)) {
                throw new DomainException('Invalid activity timing structure.');
            }
        }

        return self::fromParts(
            $data['started_at'] === null ? null : ObservationOccurrence::fromString($data['started_at']),
            $data['ended_at'] === null ? null : ObservationOccurrence::fromString($data['ended_at']),
            $data['elapsed'] === null ? null : Duration::fromString($data['elapsed'])
        );
    }

    /**
     * Creates timing from structured text
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            throw new DomainException('Invalid activity timing text.');
        }

        return self::fromArray($data);
    }

    /**
     * Returns the known start without inventing precision
     */
    public function startedAt(): ?ObservationOccurrence
    {
        return $this->startedAt;
    }

    /**
     * Returns the known end without inventing precision
     */
    public function endedAt(): ?ObservationOccurrence
    {
        return $this->endedAt;
    }

    /**
     * Returns independently measured nonnegative elapsed time
     */
    public function elapsed(): ?Duration
    {
        return $this->elapsed;
    }

    /**
     * Returns stable structured timing text
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the closed timing representation with explicit unknown values
     *
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'started_at' => $this->startedAt?->toString(),
            'ended_at'   => $this->endedAt?->toString(),
            'elapsed'    => $this->elapsed?->toString()
        ];
    }

    /**
     * Returns deliberate structured JSON
     *
     * @return array<string, string|null>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
