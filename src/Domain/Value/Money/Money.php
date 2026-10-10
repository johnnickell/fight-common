<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\Money;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Comparable;
use Fight\Common\Domain\Value\Basic\Decimal;
use Fight\Common\Domain\Value\ValueObject;
use RoundingMode;

/**
 * Class Money
 *
 * Captures one supported accounting definition with a signed native minor-unit amount
 */
final readonly class Money extends ValueObject implements Comparable
{
    /**
     * Constructs Money
     */
    private function __construct(private int $minorUnits, private Currency $currency)
    {
    }

    /**
     * Creates Money from exact signed minor units and a supported captured definition
     */
    public static function fromMinorUnits(int $minorUnits, Currency $currency): self
    {
        self::requireSupported($currency);

        return new self($minorUnits, $currency);
    }

    /**
     * Creates Money from a major-unit Decimal, with optional explicit rounding at the minor-unit boundary
     *
     * @throws DomainException When the result is fractional without rounding or exceeds native limits
     */
    public static function fromDecimal(Decimal $amount, Currency $currency, ?RoundingMode $mode = null): self
    {
        self::requireSupported($currency);
        $minor = $amount->multiply(self::factor($currency));

        return new self(self::nativeAmount($minor, $mode), $currency);
    }

    /**
     * Creates Money from only a canonical saved representation with a retained package definition
     *
     * @throws DomainException When the representation, definition or amount is unsupported
     */
    public static function fromString(string $value): static
    {
        if (preg_match('/\Amoney:v1:([A-Z]{3}):(v[1-9][0-9]*):([0-9]+):(0|-?[1-9][0-9]*)\z/', $value, $parts) !== 1) {
            throw new DomainException('Invalid Money representation');
        }

        $currency = Currency::fromDefinition($parts[1], $parts[2]);
        if ((string) $currency->accountingExponent() !== $parts[3]) {
            throw new DomainException('Money accounting scale does not match its definition');
        }

        return new self(self::nativeAmount(Decimal::fromString($parts[4])), $currency);
    }

    /**
     * Returns the exact signed native minor units
     */
    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    /**
     * Returns the captured immutable currency and definition context
     */
    public function currency(): Currency
    {
        return $this->currency;
    }

    /**
     * Returns the exact major-unit Decimal
     */
    public function toDecimal(): Decimal
    {
        return Decimal::fromString((string) $this->minorUnits)->divide(self::factor($this->currency));
    }

    /**
     * Returns the checked compatible-unit sum
     */
    public function add(self $other): self
    {
        $this->requireCompatible($other);

        return new self(self::nativeAmount($this->amount()->add($other->amount())), $this->currency);
    }

    /**
     * Returns the checked compatible-unit difference without native negation of the subtrahend
     */
    public function subtract(self $other): self
    {
        $this->requireCompatible($other);

        return new self(self::nativeAmount($this->amount()->subtract($other->amount())), $this->currency);
    }

    /**
     * Returns Money with the opposite sign, rejecting an unrepresentable positive minimum
     */
    public function negate(): self
    {
        $opposite = Decimal::fromString('0')->subtract($this->amount());

        return new self(self::nativeAmount($opposite), $this->currency);
    }

    /**
     * Returns an exact scalar product or one explicitly rounded at the minor-unit boundary
     */
    public function multiply(Decimal $scalar, ?RoundingMode $mode = null): self
    {
        return new self(self::nativeAmount($this->amount()->multiply($scalar), $mode), $this->currency);
    }

    /**
     * Returns an exact scalar quotient or one explicitly rounded at the minor-unit boundary
     *
     * Exact division rejects nonterminating quotients; explicit rounding makes one quotient decision
     */
    public function divide(Decimal $scalar, ?RoundingMode $mode = null): self
    {
        if ($mode === null) {
            $amount = $this->amount()->divide($scalar);
        } else {
            $amount = $this->amount()->divideRounded($scalar, 0, $mode);
        }

        return new self(self::nativeAmount($amount), $this->currency);
    }

    /**
     * Returns signed minor units allocated by largest remainder, ties by original index
     *
     * @param array<mixed> $weights Nonnegative native integers; at least one positive
     *
     * @return list<self>
     *
     * @throws DomainException When weights are invalid
     */
    public function allocate(array $weights): array
    {
        if ($weights === [] || !array_is_list($weights)) {
            throw new DomainException('Money allocation requires a nonempty list');
        }

        $totalWeight = Decimal::fromString('0');
        foreach ($weights as $weight) {
            if (!is_int($weight) || $weight < 0) {
                throw new DomainException('Money allocation requires nonnegative integer weights');
            }

            $totalWeight = $totalWeight->add(Decimal::fromString((string) $weight));
        }

        if ($totalWeight->toString() === '0') {
            throw new DomainException('Money allocation requires a positive weight');
        }

        $magnitude = $this->amount();
        if ($this->minorUnits < 0) {
            $magnitude = Decimal::fromString('0')->subtract($magnitude);
        }

        $shares = [];
        $remainders = [];
        $assigned = Decimal::fromString('0');
        foreach ($weights as $index => $weight) {
            $product = $magnitude->multiply(Decimal::fromString((string) $weight));
            $share = $product->divideRounded($totalWeight, 0, RoundingMode::TowardsZero);
            $shares[$index] = $share;
            $remainders[$index] = $product->subtract($share->multiply($totalWeight));
            $assigned = $assigned->add($share);
        }

        $leftover = (int) $magnitude->subtract($assigned)->toString();
        $ranking = array_keys($weights);
        usort($ranking, static fn (int $left, int $right): int =>
            $remainders[$right]->compareTo($remainders[$left]) ?: ($left <=> $right));
        for ($index = 0; $index < $leftover; $index++) {
            $position = $ranking[$index];
            $shares[$position] = $shares[$position]->add(Decimal::fromString('1'));
        }

        $result = [];
        foreach ($shares as $share) {
            $signed = $this->minorUnits < 0 ? Decimal::fromString('0')->subtract($share) : $share;
            $result[] = new self(self::nativeAmount($signed), $this->currency);
        }

        return $result;
    }

    /**
     * Returns signed amount ordering only for compatible accounting units
     */
    public function compareTo(mixed $other): int
    {
        if (!$other instanceof self) {
            throw new DomainException('Money comparison requires Money');
        }

        $this->requireCompatible($other);

        return $this->minorUnits <=> $other->minorUnits;
    }

    /**
     * Returns value equality by code, captured scale and minor units, not definition version
     */
    public function equals(mixed $object): bool
    {
        return $object instanceof self && $this->hashValue() === $object->hashValue();
    }

    /**
     * Returns the unambiguous numeric identity independent of definition revision metadata
     */
    public function hashValue(): string
    {
        return $this->currency->code().':'.$this->currency->accountingExponent().':'.$this->minorUnits;
    }

    /**
     * Returns versioned saved text with exact minor units and captured definition context
     */
    public function toString(): string
    {
        $definition = 'money:v1:'.$this->currency->code().':'.$this->currency->definitionVersion();

        return $definition.':'.$this->currency->accountingExponent().':'.$this->minorUnits;
    }

    /**
     * Rejects definitions not present with their exact exponent in the package's retained set
     */
    private static function requireSupported(Currency $currency): void
    {
        $supported = Currency::fromDefinition($currency->code(), $currency->definitionVersion());
        if ($currency->accountingExponent() !== $supported->accountingExponent()) {
            throw new DomainException('Money accounting scale does not match its definition');
        }
    }

    /**
     * Returns the supported base-ten accounting factor
     */
    private static function factor(Currency $currency): Decimal
    {
        return Decimal::fromString('1'.str_repeat('0', $currency->accountingExponent()));
    }

    /**
     * Checks integral native representability before converting any Decimal to an integer
     */
    private static function nativeAmount(Decimal $amount, ?RoundingMode $mode = null): int
    {
        if ($mode !== null) {
            $amount = $amount->round(0, $mode);
        }

        $text = $amount->toString();
        if (preg_match('/\A-?[0-9]+\z/', $text) !== 1) {
            throw new DomainException('Money requires exact minor units or explicit rounding');
        }

        if (
            $amount->compareTo(Decimal::fromString((string) PHP_INT_MIN)) < 0
            || $amount->compareTo(Decimal::fromString((string) PHP_INT_MAX)) > 0
        ) {
            throw new DomainException('Money exceeds the native integer range');
        }

        return (int) $text;
    }

    /**
     * Returns the exact Decimal minor-unit amount
     */
    private function amount(): Decimal
    {
        return Decimal::fromString((string) $this->minorUnits);
    }

    /**
     * Rejects implicit conversion between incompatible accounting units
     */
    private function requireCompatible(self $other): void
    {
        if (
            $this->currency->code() !== $other->currency->code()
            || $this->currency->accountingExponent() !== $other->currency->accountingExponent()
        ) {
            throw new DomainException('Money requires matching currency and accounting scale');
        }
    }
}
