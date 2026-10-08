<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\DateTime;

use DateTimeInterface;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Comparable;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class Date
 */
final readonly class Date extends ValueObject implements Comparable
{
    /**
     * Constructs Date
     *
     * @throws DomainException When the components are outside the supported Gregorian calendar
     */
    private function __construct(private int $year, private int $month, private int $day)
    {
        if ($year < 1 || $year > 9999 || !checkdate($month, $day, $year)) {
            throw new DomainException('Invalid Gregorian date');
        }
    }

    /**
     * Creates a date from Gregorian calendar components without rollover
     *
     * @throws DomainException When the components are outside the supported Gregorian calendar
     */
    public static function fromParts(int $year, int $month, int $day): static
    {
        return new static($year, $month, $day);
    }

    /**
     * Creates a date from its exact YYYY-MM-DD representation
     *
     * @throws DomainException When the representation or date is invalid
     */
    public static function fromString(string $value): static
    {
        if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value) !== 1) {
            throw new DomainException('Invalid date representation');
        }

        return new static((int) substr($value, 0, 4), (int) substr($value, 5, 2), (int) substr($value, 8, 2));
    }

    /**
     * Creates a date by copying the supplied timestamp's own local calendar components
     *
     * @throws DomainException When the extracted date is outside the supported calendar
     */
    public static function fromNative(DateTimeInterface $value): static
    {
        return new static((int) $value->format('Y'), (int) $value->format('n'), (int) $value->format('j'));
    }

    /**
     * Returns the year
     */
    public function year(): int
    {
        return $this->year;
    }

    /**
     * Returns the month
     */
    public function month(): int
    {
        return $this->month;
    }

    /**
     * Returns the day of the month
     */
    public function day(): int
    {
        return $this->day;
    }

    /**
     * Returns the proleptic Gregorian weekday without a timezone or timestamp
     */
    public function weekDay(): WeekDay
    {
        // Gregorian 0001-01-01 is Monday; count whole days since that calendar origin.
        $previousYear = $this->year - 1;
        $days = 365 * $previousYear + intdiv($previousYear, 4) - intdiv($previousYear, 100);
        $days += intdiv($previousYear, 400);
        $monthOffsets = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $days += $monthOffsets[$this->month - 1] + $this->day - 1;
        if ($this->month > 2 && checkdate(2, 29, $this->year)) {
            $days++;
        }

        return WeekDay::from($days % 7 + 1);
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    /**
     * Returns chronological date ordering
     *
     * @throws DomainException When the other value is not a Date
     */
    public function compareTo(mixed $other): int
    {
        if (!$other instanceof self) {
            throw new DomainException('Date comparison requires a Date');
        }

        return strcmp($this->toString(), $other->toString()) <=> 0;
    }
}
