<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\Basic;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Comparable;
use Fight\Common\Domain\Value\ValueObject;
use RoundingMode;

/**
 * Class Decimal
 *
 * An exact, bounded base-ten number without a floating-point backend
 */
final readonly class Decimal extends ValueObject implements Comparable
{
    private const int MAX_DIGITS = 1024;
    private const int MAX_SCALE = 1024;

    /**
     * Constructs Decimal
     */
    private function __construct(
        private string $coefficient,
        private int $scale,
        private int $sign
    ) {
    }

    /**
     * Creates an exact number from plain ASCII decimal text
     *
     * @throws DomainException When the input or normalized value exceeds supported bounds
     */
    public static function fromString(string $value): static
    {
        if (strlen($value) > 4096 || preg_match('/\A([+-]?)([0-9]+)(?:\.([0-9]+))?\z/D', $value, $parts) !== 1) {
            throw new DomainException('Invalid decimal representation');
        }

        $fraction = $parts[3] ?? '';

        return self::create($parts[2].$fraction, strlen($fraction), $parts[1] === '-' ? -1 : 1);
    }

    /**
     * Returns the canonical signed plain decimal representation
     */
    public function toString(): string
    {
        if ($this->sign === 0) {
            return '0';
        }

        $digits = $this->coefficient;
        if ($this->scale > 0) {
            $digits = str_pad($digits, $this->scale + 1, '0', STR_PAD_LEFT);
            $digits = substr($digits, 0, -$this->scale).'.'.substr($digits, -$this->scale);
        }

        return ($this->sign < 0 ? '-' : '').$digits;
    }

    /**
     * Returns numeric ordering independent of decimal spelling
     *
     * @throws DomainException When comparing with a different type
     */
    public function compareTo(mixed $other): int
    {
        if (!$other instanceof self) {
            throw new DomainException('Decimal comparison requires a Decimal');
        }

        if ($this->sign !== $other->sign) {
            return $this->sign <=> $other->sign;
        }

        if ($this->sign === 0) {
            return 0;
        }

        $scale = max($this->scale, $other->scale);
        $comparison = self::compareDigits(
            $this->coefficient.str_repeat('0', $scale - $this->scale),
            $other->coefficient.str_repeat('0', $scale - $other->scale)
        );

        return $this->sign * $comparison;
    }

    /**
     * Returns the exact sum
     */
    public function add(self $other): static
    {
        if ($this->sign === 0) {
            return new self($other->coefficient, $other->scale, $other->sign);
        }

        if ($other->sign === 0) {
            return new self($this->coefficient, $this->scale, $this->sign);
        }

        $scale = max($this->scale, $other->scale);
        $left = $this->coefficient.str_repeat('0', $scale - $this->scale);
        $right = $other->coefficient.str_repeat('0', $scale - $other->scale);
        if ($this->sign === $other->sign) {
            return self::create(self::sumDigits($left, $right), $scale, $this->sign);
        }

        $comparison = self::compareDigits($left, $right);
        if ($comparison >= 0) {
            return self::create(self::differenceDigits($left, $right), $scale, $this->sign);
        }

        return self::create(self::differenceDigits($right, $left), $scale, $other->sign);
    }

    /**
     * Returns the exact difference
     */
    public function subtract(self $other): static
    {
        return $this->add(new self($other->coefficient, $other->scale, -$other->sign));
    }

    /**
     * Returns the exact product or rejects an unsupported result
     */
    public function multiply(self $other): static
    {
        if ($this->sign === 0 || $other->sign === 0) {
            return self::create('0', 0, 1);
        }

        $product = '0';
        for ($index = strlen($other->coefficient) - 1; $index >= 0; $index--) {
            $carry = 0;
            $row = '';
            for ($digit = strlen($this->coefficient) - 1; $digit >= 0; $digit--) {
                $amount = ((int) $this->coefficient[$digit] * (int) $other->coefficient[$index]) + $carry;
                $row = ($amount % 10).$row;
                $carry = intdiv($amount, 10);
            }

            $product = self::sumDigits(
                $product,
                ($carry > 0 ? $carry : '').$row.str_repeat('0', strlen($other->coefficient) - 1 - $index)
            );
        }

        return self::create($product, $this->scale + $other->scale, $this->sign * $other->sign);
    }

    /**
     * Returns a finite exact quotient without implicit rounding
     *
     * @throws DomainException When division is undefined, nonterminating or out of bounds
     */
    public function divide(self $other): static
    {
        $this->requireDivisor($other);
        if ($this->sign === 0) {
            return self::create('0', 0, 1);
        }

        [$numerator, $denominator] = $this->ratio($other);
        [$quotient, $remainder] = self::divideDigits($numerator, $denominator);
        $scale = 0;
        while ($remainder !== '0') {
            if ($scale === self::MAX_SCALE) {
                throw new DomainException('Decimal quotient is nonterminating or exceeds supported scale');
            }

            [$digit, $remainder] = self::divideDigits($remainder.'0', $denominator);
            $quotient .= $digit;
            $scale++;
        }

        return self::create($quotient, $scale, $this->sign * $other->sign);
    }

    /**
     * Returns the quotient rounded once at the requested decimal scale
     *
     * @throws DomainException When division is undefined or the scale/result is unsupported
     */
    public function divideRounded(self $other, int $scale, RoundingMode $mode): static
    {
        self::requireScale($scale);
        $this->requireDivisor($other);
        if ($this->sign === 0) {
            return self::create('0', 0, 1);
        }

        [$numerator, $denominator] = $this->ratio($other);
        [$digits, $remainder] = self::divideDigits($numerator.str_repeat('0', $scale), $denominator);
        $sign = $this->sign * $other->sign;

        return self::create(self::roundedDigits($digits, $remainder, $denominator, $sign, $mode), $scale, $sign);
    }

    /**
     * Returns this number rounded once at the requested decimal scale
     *
     * @throws DomainException When the scale/result is unsupported
     */
    public function round(int $scale, RoundingMode $mode): static
    {
        self::requireScale($scale);
        if ($scale >= $this->scale || $this->sign === 0) {
            return new self($this->coefficient, $this->scale, $this->sign);
        }

        $shift = $this->scale - $scale;
        $digits = substr($this->coefficient, 0, -$shift);
        $remainder = substr($this->coefficient, -$shift);

        return self::create(
            self::roundedDigits(
                $digits === '' ? '0' : $digits,
                $remainder,
                '1'.str_repeat('0', $shift),
                $this->sign,
                $mode
            ),
            $scale,
            $this->sign
        );
    }

    /**
     * Creates a normalized value from nonnegative integer digits and a base-ten scale
     */
    private static function create(string $digits, int $scale, int $sign): static
    {
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return new self('0', 0, 0);
        }

        $trailing = min($scale, strlen($digits) - strlen(rtrim($digits, '0')));
        if ($trailing > 0) {
            $digits = substr($digits, 0, -$trailing);
            $scale -= $trailing;
        }

        if ($scale > self::MAX_SCALE || strlen($digits) > self::MAX_DIGITS) {
            throw new DomainException('Decimal exceeds supported precision');
        }

        return new self($digits, $scale, $sign);
    }

    /**
     * Validates the requested nonnegative scale
     */
    private static function requireScale(int $scale): void
    {
        if ($scale < 0 || $scale > self::MAX_SCALE) {
            throw new DomainException('Unsupported decimal scale');
        }
    }

    /**
     * Returns decimal digit ordering without integer conversion
     */
    private static function compareDigits(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';

        return (strlen($left) <=> strlen($right)) ?: (strcmp($left, $right) <=> 0);
    }

    /**
     * Adds two nonnegative integer strings
     */
    private static function sumDigits(string $left, string $right): string
    {
        $result = '';
        $carry = 0;
        for ($i = strlen($left) - 1, $j = strlen($right) - 1; $i >= 0 || $j >= 0 || $carry > 0; $i--, $j--) {
            $sum = ($i >= 0 ? (int) $left[$i] : 0) + ($j >= 0 ? (int) $right[$j] : 0) + $carry;
            $result = ($sum % 10).$result;
            $carry = intdiv($sum, 10);
        }

        return ltrim($result, '0') ?: '0';
    }

    /**
     * Returns the difference of nonnegative integer strings in descending order
     */
    private static function differenceDigits(string $left, string $right): string
    {
        $result = '';
        $borrow = 0;
        for ($i = strlen($left) - 1, $j = strlen($right) - 1; $i >= 0; $i--, $j--) {
            $difference = (int) $left[$i] - ($j >= 0 ? (int) $right[$j] : 0) - $borrow;
            $borrow = $difference < 0 ? 1 : 0;
            $result = (($difference + 10) % 10).$result;
        }

        return ltrim($result, '0') ?: '0';
    }

    /**
     * Returns the quotient and exact remainder of nonnegative integer strings
     *
     * @return array{string, string}
     */
    private static function divideDigits(string $numerator, string $denominator): array
    {
        $quotient = '';
        $remainder = '0';
        foreach (str_split($numerator) as $digit) {
            $remainder = ltrim($remainder.$digit, '0') ?: '0';
            $count = 0;
            while (self::compareDigits($remainder, $denominator) >= 0) {
                $remainder = self::differenceDigits($remainder, $denominator);
                $count++;
            }

            $quotient .= $count;
        }

        return [ltrim($quotient, '0') ?: '0', $remainder];
    }

    /**
     * Applies one native rounding decision to exact quotient and remainder digits
     */
    private static function roundedDigits(
        string $digits,
        string $remainder,
        string $denominator,
        int $sign,
        RoundingMode $mode
    ): string {
        $remainder = ltrim($remainder, '0') ?: '0';
        if ($remainder === '0') {
            return $digits;
        }

        $half = self::compareDigits(self::sumDigits($remainder, $remainder), $denominator);
        $odd = ((int) $digits[strlen($digits) - 1]) % 2 === 1;
        $increment = match ($mode) {
            RoundingMode::AwayFromZero => true,
            RoundingMode::TowardsZero => false,
            RoundingMode::PositiveInfinity => $sign > 0,
            RoundingMode::NegativeInfinity => $sign < 0,
            RoundingMode::HalfAwayFromZero => $half >= 0,
            RoundingMode::HalfTowardsZero => $half > 0,
            RoundingMode::HalfEven => $half > 0 || ($half === 0 && $odd),
            RoundingMode::HalfOdd => $half > 0 || ($half === 0 && !$odd),
        };

        return $increment ? self::sumDigits($digits, '1') : $digits;
    }

    /**
     * Rejects a zero divisor
     */
    private function requireDivisor(self $other): void
    {
        if ($other->sign === 0) {
            throw new DomainException('Division by zero');
        }
    }

    /**
     * Returns exact integer numerator and denominator for the quotient
     *
     * @return array{string, string}
     */
    private function ratio(self $other): array
    {
        return [
            $this->coefficient.str_repeat('0', $other->scale),
            $other->coefficient.str_repeat('0', $this->scale)
        ];
    }
}
