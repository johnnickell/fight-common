<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Money;

use Error;
use Fight\Common\Domain\Collection\HashSet;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\Decimal;
use Fight\Common\Domain\Value\Basic\StringObject;
use Fight\Common\Domain\Value\Money\Currency;
use Fight\Common\Domain\Value\Money\CurrencyDefinitions;
use Fight\Common\Domain\Value\Money\Money;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RoundingMode;

#[CoversClass(Money::class)]
class MoneyTest extends UnitTestCase
{
    public function test_that_minor_construction_captures_currency_exponent_and_native_signed_limits(): void
    {
        foreach (['JPY' => 0, 'USD' => 2, 'KWD' => 3] as $code => $scale) {
            $currency = Currency::fromCode($code);
            foreach ([PHP_INT_MIN, -1, 0, 1, PHP_INT_MAX] as $amount) {
                $money = Money::fromMinorUnits($amount, $currency);
                self::assertSame($amount, $money->minorUnits());
                self::assertSame($currency, $money->currency());
                self::assertSame($scale, $money->currency()->accountingExponent());
                self::assertTrue($money->equals(Money::fromString($money->toString())));
            }
        }
    }

    public function test_that_exact_major_conversion_and_serialization_preserve_signed_meaning(): void
    {
        $usd = Currency::fromCode('USD');
        $kwd = Currency::fromCode('KWD');
        $jpy = Currency::fromCode('JPY');
        self::assertSame(123, Money::fromDecimal(Decimal::fromString('1.23'), $usd)->minorUnits());
        self::assertSame(-1234, Money::fromDecimal(Decimal::fromString('-1.234'), $kwd)->minorUnits());
        self::assertSame(2, Money::fromDecimal(Decimal::fromString('2.000'), $jpy)->minorUnits());
        self::assertSame('1.23', Money::fromMinorUnits(123, $usd)->toDecimal()->toString());
        self::assertSame('-0.01', Money::fromMinorUnits(-1, $usd)->toDecimal()->toString());
        self::assertSame('money:v1:USD:v1:2:-123', Money::fromMinorUnits(-123, $usd)->toString());
        $savedJson = json_encode(Money::fromMinorUnits(-123, $usd), JSON_THROW_ON_ERROR);
        $decoded = json_decode($savedJson, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('money:v1:USD:v1:2:-123', $decoded);
        self::assertSame(-123, Money::fromString($decoded)->minorUnits());
        self::assertSame(PHP_INT_MIN, Money::fromDecimal(
            Money::fromMinorUnits(PHP_INT_MIN, $kwd)->toDecimal(),
            $kwd
        )->minorUnits());
    }

    #[DataProvider('roundingCases')]
    public function test_that_rounding_is_one_explicit_decision_at_the_minor_boundary(
        string $amount,
        RoundingMode $mode,
        int $expected
    ): void {
        self::assertSame($expected, Money::fromDecimal(
            Decimal::fromString($amount), Currency::fromCode('USD'), $mode
        )->minorUnits());
    }

    /**
     * @return iterable<string, array{string, RoundingMode, int}>
     */
    public static function roundingCases(): iterable
    {
        yield 'half away positive' => ['1.005', RoundingMode::HalfAwayFromZero, 101];
        yield 'half away negative' => ['-1.005', RoundingMode::HalfAwayFromZero, -101];
        yield 'half towards' => ['1.005', RoundingMode::HalfTowardsZero, 100];
        yield 'half even' => ['1.005', RoundingMode::HalfEven, 100];
        yield 'half odd' => ['1.005', RoundingMode::HalfOdd, 101];
        yield 'away' => ['-1.001', RoundingMode::AwayFromZero, -101];
        yield 'towards' => ['-1.009', RoundingMode::TowardsZero, -100];
        yield 'positive infinity' => ['-1.009', RoundingMode::PositiveInfinity, -100];
        yield 'negative infinity' => ['-1.001', RoundingMode::NegativeInfinity, -101];
        yield 'single rounding' => ['1.0049', RoundingMode::HalfAwayFromZero, 100];
    }

    public function test_that_fractional_minor_units_require_an_explicit_mode(): void
    {
        $this->expectException(DomainException::class);

        Money::fromDecimal(Decimal::fromString('1.005'), Currency::fromCode('USD'));
    }

    public function test_that_checked_arithmetic_supports_cancellation_and_minimum_identity(): void
    {
        $usd = Currency::fromCode('USD');
        $minimum = Money::fromMinorUnits(PHP_INT_MIN, $usd);
        $one = Money::fromMinorUnits(1, $usd);
        self::assertSame(PHP_INT_MIN, $minimum->add(Money::fromMinorUnits(0, $usd))->minorUnits());
        self::assertSame(PHP_INT_MIN + 1, $minimum->add($one)->minorUnits());
        self::assertSame(-1, $minimum->subtract(Money::fromMinorUnits(PHP_INT_MIN + 1, $usd))->minorUnits());
        self::assertSame(0, $minimum->subtract($minimum)->minorUnits());
        self::assertSame(0, $one->subtract($one)->minorUnits());
        self::assertSame(-1, $one->negate()->minorUnits());
        self::assertSame(1, $one->compareTo(Money::fromMinorUnits(0, $usd)));
        self::assertSame(-1, $minimum->compareTo($one));
        self::assertSame(0, $one->compareTo(Money::fromMinorUnits(1, $usd)));
    }

    public function test_that_minimum_negation_rejects_without_native_float_promotion(): void
    {
        $this->expectException(DomainException::class);

        Money::fromMinorUnits(PHP_INT_MIN, Currency::fromCode('USD'))->negate();
    }

    #[DataProvider('overflowOperations')]
    public function test_that_unrepresentable_arithmetic_and_conversion_reject(string $operation): void
    {
        $usd = Currency::fromCode('USD');
        $maximum = Money::fromMinorUnits(PHP_INT_MAX, $usd);
        $minimum = Money::fromMinorUnits(PHP_INT_MIN, $usd);
        $one = Money::fromMinorUnits(1, $usd);
        $this->expectException(DomainException::class);

        match ($operation) {
            'add' => $maximum->add($one),
            'subtract' => $minimum->subtract($one),
            'multiply' => $maximum->multiply(Decimal::fromString('2')),
            'divide' => $minimum->divide(Decimal::fromString('0.5')),
            'major' => Money::fromDecimal(Decimal::fromString((string) PHP_INT_MAX), $usd),
            'rounded' => $maximum->multiply(Decimal::fromString('1.0000001'), RoundingMode::AwayFromZero)
        };
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function overflowOperations(): iterable
    {
        foreach (['add', 'subtract', 'multiply', 'divide', 'major', 'rounded'] as $operation) {
            yield $operation => [$operation];
        }
    }

    public function test_that_scalar_calculation_is_exact_or_explicitly_rounded_only_once(): void
    {
        $usd = Currency::fromCode('USD');
        $five = Money::fromMinorUnits(5, $usd);
        self::assertSame(5, $five->multiply(Decimal::fromString('1'))->minorUnits());
        $minimum = Money::fromMinorUnits(PHP_INT_MIN, $usd);
        self::assertSame(PHP_INT_MIN, $minimum->multiply(Decimal::fromString('1'))->minorUnits());
        self::assertSame(0, $minimum->multiply(Decimal::fromString('0'))->minorUnits());
        self::assertSame(4, $five->multiply(Decimal::fromString('0.8'))->minorUnits());
        self::assertSame(2, $five->divide(Decimal::fromString('2.5'))->minorUnits());
        self::assertSame(2, $five->divide(Decimal::fromString('3'), RoundingMode::HalfEven)->minorUnits());
        self::assertSame(-2, $five->negate()->divide(Decimal::fromString('3'), RoundingMode::HalfOdd)->minorUnits());
        self::assertSame(2, $five->multiply(Decimal::fromString('0.30002'), RoundingMode::HalfAwayFromZero)
            ->minorUnits());
    }

    public function test_that_fractional_scalar_result_rejects_without_rounding(): void
    {
        $this->expectException(DomainException::class);

        Money::fromMinorUnits(5, Currency::fromCode('USD'))->multiply(Decimal::fromString('0.3'));
    }

    public function test_that_nonterminating_exact_division_rejects_without_rounding(): void
    {
        $this->expectException(DomainException::class);

        Money::fromMinorUnits(1, Currency::fromCode('USD'))->divide(Decimal::fromString('3'));
    }

    public function test_that_division_by_zero_rejects(): void
    {
        $this->expectException(DomainException::class);

        Money::fromMinorUnits(1, Currency::fromCode('USD'))->divide(
            Decimal::fromString('0'), RoundingMode::HalfEven
        );
    }

    public function test_that_incompatible_currencies_and_scales_reject_calculation_and_order(): void
    {
        $usd = Money::fromMinorUnits(1, Currency::fromCode('USD'));
        $eur = Money::fromMinorUnits(1, Currency::fromCode('EUR'));
        $definitions = CurrencyDefinitions::fromDefinitions(['USD' => ['v1' => 2, 'v2' => 3]], ['USD' => 'v2']);
        $rescaled = Money::fromMinorUnits(1, Currency::fromDefinitions('USD', 'v2', $definitions));
        foreach ([$eur, $rescaled] as $other) {
            foreach (['add', 'subtract', 'compareTo'] as $operation) {
                try {
                    $usd->$operation($other);
                    self::fail('Incompatible Money must reject '.$operation);
                } catch (DomainException) {
                    self::assertSame(1, $usd->minorUnits());
                }
            }
        }

        $this->expectException(DomainException::class);
        $usd->compareTo('USD');
    }

    public function test_that_numeric_identity_ignores_metadata_only_versions_and_supports_real_hash_collections(): void
    {
        $definitions = CurrencyDefinitions::fromDefinitions(['USD' => ['v1' => 2, 'v2' => 2]], ['USD' => 'v2']);
        $old = Money::fromMinorUnits(12, Currency::fromDefinitions('USD', 'v1', $definitions));
        $new = Money::fromMinorUnits(12, Currency::fromDefinitions('USD', 'v2', $definitions));
        self::assertTrue($old->equals($old));
        self::assertTrue($old->equals($new));
        self::assertSame($old->hashValue(), $new->hashValue());
        self::assertNotSame($old->toString(), $new->toString());
        self::assertFalse($old->equals(Money::fromMinorUnits(13, $old->currency())));
        self::assertFalse($old->equals(Money::fromMinorUnits(12, Currency::fromCode('EUR'))));
        self::assertFalse($old->equals(StringObject::fromString($old->toString())));
        self::assertFalse($old->equals(null));
        $set = HashSet::of(Money::class);
        $set->add($old);
        $set->add($new);
        $set->add(Money::fromMinorUnits(13, $old->currency()));
        self::assertSame(2, $set->count());
        self::assertTrue($set->contains($new));
        $set->remove($new);
        self::assertFalse($set->contains($old));
    }

    public function test_that_saved_reader_retains_scale_through_controlled_definition_maintenance(): void
    {
        $original = CurrencyDefinitions::fromDefinitions(['USD' => ['v1' => 2]], ['USD' => 'v1']);
        $updated = $original->revised(
            ['USD' => ['v1' => 2, 'v2' => 3], 'JPY' => ['v1' => 0]],
            ['USD' => 'v2', 'JPY' => 'v1']
        );
        $saved = Money::fromMinorUnits(123, Currency::fromDefinitions('USD', 'v1', $original))->toString();
        $restored = Money::fromSavedWithDefinitions($saved, $updated);
        self::assertSame('money:v1:USD:v1:2:123', $saved);
        self::assertSame('1.23', $restored->toDecimal()->toString());
        self::assertSame($saved, $restored->toString());
        self::assertSame('0.123', Money::fromMinorUnits(
            123, Currency::fromDefinitions('USD', 'v2', $updated)
        )->toDecimal()->toString());
        self::assertSame('JPY', Currency::fromDefinitions('JPY', 'v1', $updated)->code());
    }

    #[DataProvider('invalidSavedValues')]
    public function test_that_saved_reader_rejects_malformed_unknown_tampered_or_out_of_range_data(string $value): void
    {
        $this->expectException(DomainException::class);

        Money::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSavedValues(): iterable
    {
        yield 'bad prefix' => ['other:v1:USD:v1:2:1'];
        yield 'version' => ['money:v2:USD:v1:2:1'];
        yield 'unknown code' => ['money:v1:BGN:v1:2:1'];
        yield 'unknown definition' => ['money:v1:USD:v2:2:1'];
        yield 'bad scale' => ['money:v1:USD:v1:3:1'];
        yield 'padded scale' => ['money:v1:USD:v1:02:1'];
        yield 'plus' => ['money:v1:USD:v1:2:+1'];
        yield 'leading zero' => ['money:v1:USD:v1:2:01'];
        yield 'negative zero' => ['money:v1:USD:v1:2:-0'];
        yield 'trailing newline' => ["money:v1:USD:v1:2:1\n"];
        yield 'floating' => ['money:v1:USD:v1:2:1.5'];
        yield 'too large' => ['money:v1:USD:v1:2:'.Decimal::fromString((string) PHP_INT_MAX)
            ->add(Decimal::fromString('1'))->toString()];
        yield 'too negative' => ['money:v1:USD:v1:2:'.Decimal::fromString((string) PHP_INT_MIN)
            ->subtract(Decimal::fromString('1'))->toString()];
    }

    #[DataProvider('invalidWeights')]
    public function test_that_allocation_validates_inputs_even_for_zero_amount(array $weights): void
    {
        $this->expectException(DomainException::class);

        Money::fromMinorUnits(0, Currency::fromCode('USD'))->allocate($weights);
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function invalidWeights(): iterable
    {
        yield 'empty' => [[]];
        yield 'all zero' => [[0, 0]];
        yield 'negative' => [[1, -1]];
        yield 'fraction' => [[1, 0.5]];
        yield 'float whole' => [[1.0]];
        yield 'string' => [['1']];
        yield 'keyed' => [[1 => 1]];
        yield 'mixed keys' => [[0 => 1, 2 => 1]];
    }

    #[DataProvider('allocations')]
    public function test_that_largest_remainder_allocation_preserves_signed_totals_and_order(
        int $amount,
        array $weights,
        array $expected
    ): void {
        $money = Money::fromMinorUnits($amount, Currency::fromCode('KWD'));
        $shares = $money->allocate($weights);
        self::assertSame($expected, array_map(static fn (Money $share): int => $share->minorUnits(), $shares));
        self::assertSame(array_keys($weights), array_keys($shares));
        $sum = Decimal::fromString('0');
        foreach ($shares as $share) {
            self::assertSame($money->currency(), $share->currency());
            $sum = $sum->add(Decimal::fromString((string) $share->minorUnits()));
        }

        self::assertSame((string) $amount, $sum->toString());
    }

    /**
     * @return iterable<string, array{int, list<int>, list<int>}>
     */
    public static function allocations(): iterable
    {
        yield 'unequal tie by index' => [11, [1, 2, 2], [2, 5, 4]];
        yield 'equal split' => [5, [1, 1, 1], [2, 2, 1]];
        yield 'negative split' => [-5, [1, 1, 1], [-2, -2, -1]];
        yield 'zero weights' => [-5, [0, 2, 0, 1], [0, -3, 0, -2]];
        yield 'zero total' => [0, [2, 0, 1], [0, 0, 0]];
        yield 'max weights' => [5, [PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX], [2, 2, 1]];
        yield 'max product' => [PHP_INT_MAX, [PHP_INT_MAX, 0], [PHP_INT_MAX, 0]];
        yield 'minimum' => [PHP_INT_MIN, [1, 1, 1], [
            intdiv(PHP_INT_MIN, 3) - 1, intdiv(PHP_INT_MIN, 3) - 1, intdiv(PHP_INT_MIN, 3)
        ]];
        yield 'minimum with max weights' => [PHP_INT_MIN, [PHP_INT_MAX, PHP_INT_MAX], [
            intdiv(PHP_INT_MIN, 2), intdiv(PHP_INT_MIN, 2)
        ]];
    }

    public function test_that_money_is_immutable(): void
    {
        $money = Money::fromMinorUnits(1, Currency::fromCode('USD'));
        try {
            $money->minorUnits = 2;
            self::fail('Readonly Money must reject mutation');
        } catch (Error) {
            self::assertSame(1, $money->minorUnits());
        }
    }
}
