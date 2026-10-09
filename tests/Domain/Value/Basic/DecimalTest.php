<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Basic;

use Fight\Common\Domain\Collection\HashSet;
use Fight\Common\Domain\Collection\SortedSet;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Comparable;
use Fight\Common\Domain\Value\Basic\Decimal;
use Fight\Common\Domain\Value\Basic\StringObject;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RoundingMode;

#[CoversClass(Decimal::class)]
class DecimalTest extends UnitTestCase
{
    #[DataProvider('representations')]
    public function test_that_decimal_text_has_exact_canonical_numeric_identity(string $input, string $canonical): void
    {
        $value = Decimal::fromString($input);
        $same = Decimal::fromString($canonical);
        self::assertInstanceOf(Comparable::class, $value);
        self::assertSame($canonical, $value->toString());
        self::assertSame($canonical, (string) $value);
        self::assertSame($canonical, $value->hashValue());
        self::assertSame(0, $value->compareTo($same));
        self::assertTrue($value->equals($value));
        self::assertTrue($value->equals($same));
        self::assertSame(json_encode($canonical, JSON_THROW_ON_ERROR), json_encode($value, JSON_THROW_ON_ERROR));
        self::assertTrue($value->equals(Decimal::fromString(json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR))));
        self::assertFalse($value->equals(StringObject::fromString($canonical)));
        self::assertFalse($value->equals($canonical));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function representations(): iterable
    {
        yield ['+01.000', '1'];
        yield ['-000.00000', '0'];
        yield ['000000', '0'];
        yield ['-0.0100', '-0.01'];
        yield ['000.000100', '0.0001'];
        yield ['-'.PHP_INT_MAX.'0.1250', '-'.PHP_INT_MAX.'0.125'];
        yield ['+'.str_repeat('9', 1024), str_repeat('9', 1024)];
        yield ['0.'.str_repeat('0', 1023).'1', '0.'.str_repeat('0', 1023).'1'];
        yield [str_repeat('0', 4095).'1', '1'];
    }

    #[DataProvider('invalidTexts')]
    public function test_that_unsupported_text_and_precision_reject_without_coercion(string $text): void
    {
        $this->expectException(DomainException::class);
        Decimal::fromString($text);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidTexts(): iterable
    {
        foreach (['', '.', '+', '-', '.1', '1.', '1e2', '1E+2', '1_000', '1,000', ' 1', '1 ', "1\n", "1\0",
            '１', '1.２', '--1', '++1', str_repeat('0', 4096).'1', str_repeat('9', 1025),
            '0.'.str_repeat('0', 1024).'1'] as $text) {
            yield [$text];
        }
    }

    #[DataProvider('calculations')]
    public function test_that_exact_arithmetic_preserves_operands_and_rejects_float_approximation(
        string $method,
        string $left,
        string $right,
        string $expected
    ): void {
        $first = Decimal::fromString($left);
        $second = Decimal::fromString($right);
        $result = $first->$method($second);
        self::assertNotSame($first, $result);
        self::assertNotSame($second, $result);
        self::assertSame($expected, $result->toString());
        self::assertSame(Decimal::fromString($left)->toString(), $first->toString());
        self::assertSame(Decimal::fromString($right)->toString(), $second->toString());
    }

    /**
     * @return iterable<array{string, string, string, string}>
     */
    public static function calculations(): iterable
    {
        yield ['add', '0.1', '0.2', '0.3'];
        yield ['add', '-1000.01', '0.01', '-1000'];
        yield ['add', '999.999', '0.001', '1000'];
        yield ['add', '-3', '5', '2'];
        yield ['add', '1.25', '-1.25', '0'];
        yield ['add', '0', '-2', '-2'];
        yield ['add', '2', '0', '2'];
        yield ['subtract', '0', '-2', '2'];
        yield ['subtract', '1000', '0.001', '999.999'];
        yield ['subtract', '-2', '-3', '1'];
        yield ['subtract', '3', '5', '-2'];
        yield ['multiply', '0', '-5', '0'];
        yield ['multiply', '1.2', '-0.25', '-0.3'];
        yield ['multiply', '999', '999', '998001'];
        yield ['divide', '1', '8', '0.125'];
        yield ['divide', '-1', '40', '-0.025'];
        yield ['divide', '0', '3', '0'];
        yield ['divide', '1.25', '0.5', '2.5'];
        yield ['divide', '1', '0.00000000000000000001', '100000000000000000000'];
        yield ['divide', '1', '2'.str_repeat('0', 100), '0.'.str_repeat('0', 100).'5'];
    }

    #[DataProvider('unsupportedOperations')]
    public function test_that_exact_operations_reject_unsupported_results(string $method, string $left, string $right): void
    {
        $this->expectException(DomainException::class);
        Decimal::fromString($left)->$method(Decimal::fromString($right));
    }

    /**
     * @return iterable<array{string, string, string}>
     */
    public static function unsupportedOperations(): iterable
    {
        yield ['add', str_repeat('9', 1024), '1'];
        yield ['subtract', '-'.str_repeat('9', 1024), '1'];
        yield ['multiply', str_repeat('9', 1024), '2'];
        yield ['multiply', '0.'.str_repeat('0', 1023).'1', '0.1'];
        yield ['divide', '1', '0'];
        yield ['divide', '1', '3'];
        yield ['divide', '0.'.str_repeat('0', 1023).'1', '2'];
        yield ['divide', str_repeat('9', 1024), '0.1'];
    }

    #[DataProvider('roundedQuotients')]
    public function test_that_scaled_division_uses_exact_remainders(
        string $left,
        string $right,
        int $scale,
        RoundingMode $mode,
        string $expected
    ): void {
        self::assertSame($expected, Decimal::fromString($left)->divideRounded(Decimal::fromString($right), $scale, $mode)->toString());
    }

    /**
     * @return iterable<array{string, string, int, RoundingMode, string}>
     */
    public static function roundedQuotients(): iterable
    {
        yield ['1', '3', 2, RoundingMode::HalfEven, '0.33'];
        yield ['2', '3', 2, RoundingMode::HalfEven, '0.67'];
        yield ['1', '8', 2, RoundingMode::HalfEven, '0.12'];
        yield ['1', '8', 2, RoundingMode::HalfOdd, '0.13'];
        yield ['-1', '8', 2, RoundingMode::HalfOdd, '-0.13'];
        yield ['999.999', '1', 2, RoundingMode::HalfAwayFromZero, '1000'];
        yield ['-1', '1000', 0, RoundingMode::NegativeInfinity, '-1'];
        yield ['1', '1000', 0, RoundingMode::PositiveInfinity, '1'];
        yield ['0', '3', 1024, RoundingMode::AwayFromZero, '0'];
        yield ['1', '1', 1024, RoundingMode::HalfEven, '1'];
        yield ['1', '2'.str_repeat('0', 1023), 1024, RoundingMode::HalfAwayFromZero, '0.'.str_repeat('0', 1023).'5'];
    }

    #[DataProvider('roundingCases')]
    public function test_that_all_native_modes_round_signed_ties_and_directed_values(
        string $input,
        RoundingMode $mode,
        string $expected
    ): void {
        self::assertSame($expected, Decimal::fromString($input)->round(0, $mode)->toString());
    }

    /**
     * @return iterable<array{string, RoundingMode, string}>
     */
    public static function roundingCases(): iterable
    {
        // Independent expected outcomes in the explicit order below.
        $modes = [
            RoundingMode::AwayFromZero,
            RoundingMode::TowardsZero,
            RoundingMode::PositiveInfinity,
            RoundingMode::NegativeInfinity,
            RoundingMode::HalfAwayFromZero,
            RoundingMode::HalfTowardsZero,
            RoundingMode::HalfEven,
            RoundingMode::HalfOdd,
        ];
        $expectations = [
            '2.5' => ['3', '2', '3', '2', '3', '2', '2', '3'],
            '-2.5' => ['-3', '-2', '-2', '-3', '-3', '-2', '-2', '-3'],
            '3.5' => ['4', '3', '4', '3', '4', '3', '4', '3'],
            '-3.5' => ['-4', '-3', '-3', '-4', '-4', '-3', '-4', '-3'],
            '2.49' => ['3', '2', '3', '2', '2', '2', '2', '2'],
            '-2.49' => ['-3', '-2', '-2', '-3', '-2', '-2', '-2', '-2'],
            '2.51' => ['3', '2', '3', '2', '3', '3', '3', '3'],
            '-2.51' => ['-3', '-2', '-2', '-3', '-3', '-3', '-3', '-3'],
        ];
        foreach ($expectations as $value => $results) {
            foreach ($modes as $index => $mode) {
                yield [$value, $mode, $results[$index]];
            }
        }
        yield ['0.5', RoundingMode::HalfEven, '0'];
        yield ['-0.5', RoundingMode::HalfEven, '0'];
        yield ['9.999', RoundingMode::AwayFromZero, '10'];
        yield ['1.234', RoundingMode::HalfEven, '1'];
    }

    public function test_that_rounding_is_numeric_not_padding_and_requires_explicit_scale_and_mode(): void
    {
        self::assertSame('1.2', Decimal::fromString('1.2')->round(4, RoundingMode::HalfEven)->toString());
        self::assertSame('0', Decimal::fromString('0')->round(0, RoundingMode::AwayFromZero)->toString());
        self::assertSame('0.01', Decimal::fromString('0.005')->round(2, RoundingMode::HalfAwayFromZero)->toString());
        self::assertSame('0.1', Decimal::fromString('0.0001')->round(1, RoundingMode::AwayFromZero)->toString());
        self::assertSame('0', Decimal::fromString('-0.0001')->round(1, RoundingMode::HalfEven)->toString());
    }

    #[DataProvider('invalidScales')]
    public function test_that_invalid_scales_reject_for_both_rounded_operations(int $scale): void
    {
        $errors = [];
        foreach (['round', 'divideRounded'] as $method) {
            try {
                if ($method === 'round') {
                    Decimal::fromString('1')->round($scale, RoundingMode::HalfEven);
                } else {
                    Decimal::fromString('1')->divideRounded(Decimal::fromString('2'), $scale, RoundingMode::HalfEven);
                }
                self::fail('Scale must reject');
            } catch (DomainException $error) {
                $errors[] = $error;
            }
        }
        self::assertCount(2, $errors);
    }

    /**
     * @return iterable<array{int}>
     */
    public static function invalidScales(): iterable
    {
        yield [-1];
        yield [1025];
    }

    public function test_that_rounded_division_rejects_zero_and_result_overflow(): void
    {
        $errors = [];
        foreach ([['1', '0', 2], [str_repeat('9', 1024), '0.1', 0]] as [$left, $right, $scale]) {
            try {
                Decimal::fromString($left)->divideRounded(Decimal::fromString($right), $scale, RoundingMode::HalfEven);
                self::fail('Invalid quotient must reject');
            } catch (DomainException $error) {
                $errors[] = $error;
            }
        }
        self::assertCount(2, $errors);
    }

    public function test_that_numeric_order_and_collections_use_decimal_value_not_lexical_order(): void
    {
        $values = ['-100', '-2', '-0.01', '0', '0.01', '2', '10', '100'];
        foreach ($values as $index => $text) {
            foreach ($values as $otherIndex => $other) {
                self::assertSame($index <=> $otherIndex, Decimal::fromString($text)->compareTo(Decimal::fromString($other)));
            }
        }
        $hash = HashSet::of(Decimal::class);
        $hash->add(Decimal::fromString('1'));
        $hash->add(Decimal::fromString('+01.000'));
        $hash->add(Decimal::fromString('2'));
        self::assertSame(2, $hash->count());
        self::assertTrue($hash->contains(Decimal::fromString('1.0')));
        $hash->remove(Decimal::fromString('01'));
        self::assertSame(1, $hash->count());
        $sorted = SortedSet::comparable(Decimal::class);
        foreach (['10', '-2', '2', '+02.0'] as $text) {
            $sorted->add(Decimal::fromString($text));
        }
        self::assertSame(['-2', '2', '10'], array_map(static fn(Decimal $item): string => $item->toString(), $sorted->toArray()));
    }

    public function test_that_wrong_type_comparison_rejects_and_equality_returns_false(): void
    {
        $value = Decimal::fromString('1');
        self::assertFalse($value->equals(null));
        self::assertFalse($value->equals(StringObject::fromString('1')));
        $errors = [];
        foreach ([null, '1', StringObject::fromString('1')] as $other) {
            try {
                $value->compareTo($other);
                self::fail('Wrong type must reject');
            } catch (DomainException $error) {
                $errors[] = $error;
            }
        }
        self::assertCount(3, $errors);
    }

    public function test_that_value_is_readonly_and_ambient_precision_does_not_change_it(): void
    {
        $value = Decimal::fromString('0.12345678901234567890123456789');
        $old = ini_get('serialize_precision');
        $locale = setlocale(LC_NUMERIC, '0');
        $bcScale = function_exists('bcscale') ? bcscale() : null;
        try {
            ini_set('serialize_precision', '2');
            setlocale(LC_NUMERIC, 'C');
            if ($bcScale !== null) {
                bcscale(7);
            }
            self::assertSame('0.12345678901234567890123456789', $value->toString());
            self::assertSame('"0.12345678901234567890123456789"', json_encode($value, JSON_THROW_ON_ERROR));
            self::assertSame('0.12345678901234567890123456789', $value->hashValue());
            self::assertSame('0.3', $value->add(Decimal::fromString('0.17654321098765432109876543211'))->toString());
            if ($bcScale !== null) {
                self::assertSame(7, bcscale());
            }
        } finally {
            ini_set('serialize_precision', $old);
            setlocale(LC_NUMERIC, $locale);
            if ($bcScale !== null) {
                bcscale($bcScale);
            }
        }
        self::assertTrue((new \ReflectionClass($value))->isReadOnly());
    }

}
