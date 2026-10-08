<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\DateTime;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Comparable;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class Duration
 *
 * Represents signed fixed elapsed time, not calendar or wall-clock arithmetic
 */
final readonly class Duration extends ValueObject implements Comparable
{
    /**
     * Constructs Duration
     */
    private function __construct(private int $microseconds)
    {
    }

    /**
     * Creates a duration from signed integer microseconds within native bounds
     */
    public static function fromMicroseconds(int $microseconds): static
    {
        return new static($microseconds);
    }

    /**
     * Creates a duration from exact integer milliseconds
     *
     * @throws DomainException When scaling would exceed the native integer range
     */
    public static function fromMilliseconds(int $milliseconds): static
    {
        return self::fromUnits($milliseconds, 1000);
    }

    /**
     * Creates a duration from exact integer seconds
     *
     * @throws DomainException When scaling would exceed the native integer range
     */
    public static function fromSeconds(int $seconds): static
    {
        return self::fromUnits($seconds, 1000000);
    }

    /**
     * Creates a duration from canonical signed decimal microseconds followed by us
     *
     * @throws DomainException When the spelling is noncanonical or outside native integer bounds
     */
    public static function fromString(string $value): static
    {
        if (preg_match('/\A(0|-?[1-9][0-9]*)us\z/', $value, $matches) !== 1) {
            throw new DomainException('Invalid duration representation');
        }

        $amount = $matches[1];
        $digits = $amount;
        $limit = (string) PHP_INT_MAX;
        if ($amount[0] === '-') {
            $digits = substr($amount, 1);
            $limit = substr((string) PHP_INT_MIN, 1);
        }

        if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            throw new DomainException('Duration exceeds the native integer range');
        }

        return new static((int) $amount);
    }

    /**
     * Returns the exact signed total microseconds
     */
    public function toMicroseconds(): int
    {
        return $this->microseconds;
    }

    /**
     * Returns exact integer milliseconds without truncation or rounding
     *
     * @throws DomainException When the duration contains fractional milliseconds
     */
    public function toMilliseconds(): int
    {
        return $this->toUnits(1000);
    }

    /**
     * Returns exact integer seconds without truncation or rounding
     *
     * @throws DomainException When the duration contains fractional seconds
     */
    public function toSeconds(): int
    {
        return $this->toUnits(1000000);
    }

    /**
     * Returns a new duration containing the exact sum
     *
     * @throws DomainException When the sum would exceed the native integer range
     */
    public function add(self $other): static
    {
        $amount = $other->microseconds;
        if (
            ($amount > 0 && $this->microseconds > PHP_INT_MAX - $amount)
            || ($amount < 0 && $this->microseconds < PHP_INT_MIN - $amount)
        ) {
            throw new DomainException('Duration addition exceeds the native integer range');
        }

        return new static($this->microseconds + $amount);
    }

    /**
     * Returns a new duration containing the exact difference without negating the subtrahend
     *
     * @throws DomainException When the difference would exceed the native integer range
     */
    public function subtract(self $other): static
    {
        $amount = $other->microseconds;
        if (
            ($amount > 0 && $this->microseconds < PHP_INT_MIN + $amount)
            || ($amount < 0 && $this->microseconds > PHP_INT_MAX + $amount)
        ) {
            throw new DomainException('Duration subtraction exceeds the native integer range');
        }

        return new static($this->microseconds - $amount);
    }

    /**
     * Returns a new duration with the opposite sign
     *
     * @throws DomainException When negating PHP_INT_MIN would exceed the native integer range
     */
    public function negate(): static
    {
        if ($this->microseconds === PHP_INT_MIN) {
            throw new DomainException('Duration negation exceeds the native integer range');
        }

        return new static(-$this->microseconds);
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return $this->microseconds.'us';
    }

    /**
     * Returns signed numeric duration ordering
     *
     * @throws DomainException When the other value is not a Duration
     */
    public function compareTo(mixed $other): int
    {
        if (!$other instanceof self) {
            throw new DomainException('Duration comparison requires a Duration');
        }

        return $this->microseconds <=> $other->microseconds;
    }

    /**
     * Creates a duration by checked scaling before multiplication can promote to float
     *
     * @throws DomainException When scaling would exceed the native integer range
     */
    private static function fromUnits(int $amount, int $factor): static
    {
        if ($amount < intdiv(PHP_INT_MIN, $factor) || $amount > intdiv(PHP_INT_MAX, $factor)) {
            throw new DomainException('Duration scaling exceeds the native integer range');
        }

        return new static($amount * $factor);
    }

    /**
     * Returns an exact coarser-unit quotient without discarding signed remainders
     *
     * @throws DomainException When the duration contains a fractional unit
     */
    private function toUnits(int $factor): int
    {
        if ($this->microseconds % $factor !== 0) {
            throw new DomainException('Duration conversion requires an exact integer result');
        }

        return intdiv($this->microseconds, $factor);
    }
}
