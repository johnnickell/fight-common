<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\DateTime;

use DateTimeInterface;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Comparable;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class Time
 */
final readonly class Time extends ValueObject implements Comparable
{
    /**
     * Constructs Time
     *
     * @throws DomainException When any local time component is outside its supported bounds
     */
    private function __construct(private int $hour, private int $minute, private int $second, private int $microsecond)
    {
        if (
            $hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59
            || $microsecond < 0 || $microsecond > 999999
        ) {
            throw new DomainException('Invalid local time');
        }
    }

    /**
     * Creates a local time from exact integer components without rollover
     *
     * @throws DomainException When any local time component is outside its supported bounds
     */
    public static function fromParts(int $hour, int $minute, int $second, int $microsecond = 0): static
    {
        return new static($hour, $minute, $second, $microsecond);
    }

    /**
     * Creates a time from its exact HH:MM:SS.ffffff representation
     *
     * @throws DomainException When the representation or time is invalid
     */
    public static function fromString(string $value): static
    {
        if (preg_match('/\A[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{6}\z/', $value) !== 1) {
            throw new DomainException('Invalid time representation');
        }

        return new static(
            (int) substr($value, 0, 2),
            (int) substr($value, 3, 2),
            (int) substr($value, 6, 2),
            (int) substr($value, 9, 6)
        );
    }

    /**
     * Creates a time by copying the supplied timestamp's own local components and microseconds
     */
    public static function fromNative(DateTimeInterface $value): static
    {
        return new static(
            (int) $value->format('G'),
            (int) $value->format('i'),
            (int) $value->format('s'),
            (int) $value->format('u')
        );
    }

    /**
     * Returns the hour
     */
    public function hour(): int
    {
        return $this->hour;
    }

    /**
     * Returns the minute
     */
    public function minute(): int
    {
        return $this->minute;
    }

    /**
     * Returns the second
     */
    public function second(): int
    {
        return $this->second;
    }

    /**
     * Returns the microsecond
     */
    public function microsecond(): int
    {
        return $this->microsecond;
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return sprintf('%02d:%02d:%02d.%06d', $this->hour, $this->minute, $this->second, $this->microsecond);
    }

    /**
     * Returns chronological local time ordering
     *
     * @throws DomainException When the other value is not a Time
     */
    public function compareTo(mixed $other): int
    {
        if (!$other instanceof self) {
            throw new DomainException('Time comparison requires a Time');
        }

        return strcmp($this->toString(), $other->toString()) <=> 0;
    }
}
