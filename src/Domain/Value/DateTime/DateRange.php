<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\DateTime;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class DateRange
 *
 * Represents an inclusive calendar interval without time or timezone semantics
 */
final readonly class DateRange extends ValueObject
{
    /**
     * Constructs DateRange
     *
     * @throws DomainException When the start follows the end
     */
    private function __construct(private Date $start, private Date $end)
    {
        if ($start->compareTo($end) > 0) {
            throw new DomainException('Date range start follows end');
        }
    }

    /**
     * Creates an inclusive range from ordered calendar dates
     *
     * @throws DomainException When the start follows the end
     */
    public static function fromDates(Date $start, Date $end): static
    {
        return new static($start, $end);
    }

    /**
     * Creates a range from its exact versioned calendar representation
     *
     * @throws DomainException When the representation, dates or ordering are invalid
     */
    public static function fromString(string $value): static
    {
        $pattern = '/\Adate-range:v1:([0-9]{4}-[0-9]{2}-[0-9]{2}):([0-9]{4}-[0-9]{2}-[0-9]{2})\z/';
        if (preg_match($pattern, $value, $matches) !== 1) {
            throw new DomainException('Invalid date range representation');
        }

        return new static(Date::fromString($matches[1]), Date::fromString($matches[2]));
    }

    /**
     * Returns the inclusive starting date
     */
    public function start(): Date
    {
        return $this->start;
    }

    /**
     * Returns the inclusive ending date
     */
    public function end(): Date
    {
        return $this->end;
    }

    /**
     * Returns whether the candidate lies between the endpoints, including both endpoints
     */
    public function contains(Date $candidate): bool
    {
        return $this->start->compareTo($candidate) <= 0 && $candidate->compareTo($this->end) <= 0;
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return 'date-range:v1:'.$this->start->toString().':'.$this->end->toString();
    }
}
