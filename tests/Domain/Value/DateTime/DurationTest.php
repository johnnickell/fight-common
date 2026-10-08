<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\DateTime;

use Error;
use Fight\Common\Domain\Collection\HashSet;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Comparable;
use Fight\Common\Domain\Value\Basic\StringObject;
use Fight\Common\Domain\Value\DateTime\Duration;
use Fight\Common\Domain\Value\DateTime\Time;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use TypeError;

#[CoversClass(Duration::class)]
class DurationTest extends UnitTestCase
{
    #[DataProvider('microsecondAmounts')]
    public function test_that_signed_microseconds_reconstruct_exactly_as_value_and_json(int $amount): void
    {
        $duration = Duration::fromMicroseconds($amount);
        $text = $amount.'us';
        $restored = Duration::fromString($text);

        self::assertInstanceOf(Comparable::class, $duration);
        self::assertSame($amount, $duration->toMicroseconds());
        self::assertSame($amount, $restored->toMicroseconds());
        self::assertNotSame($duration, $restored);
        self::assertTrue($duration->equals($duration));
        self::assertTrue($duration->equals($restored));
        self::assertSame($text, $duration->toString());
        self::assertSame($text, (string) $duration);
        self::assertSame($text, $duration->hashValue());
        self::assertSame($duration->hashValue(), $restored->hashValue());
        self::assertSame(json_encode($text, JSON_THROW_ON_ERROR), json_encode($duration, JSON_THROW_ON_ERROR));
        self::assertTrue($duration->equals(Duration::fromString(json_decode(
            json_encode($duration, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR
        ))));
        self::assertFalse($duration->equals(StringObject::fromString($text)));
        self::assertFalse($duration->equals($text));
        self::assertFalse($duration->equals(null));
        self::assertFalse($duration->equals(Time::fromParts(0, 0, 0)));
    }

    /**
     * @return iterable<array{int}>
     */
    public static function microsecondAmounts(): iterable
    {
        foreach ([PHP_INT_MIN, PHP_INT_MIN + 1, -1000001, -1000, -10, -1, 0, 1, 10, 1000, 1000001,
            PHP_INT_MAX - 1, PHP_INT_MAX] as $amount) {
            yield [$amount];
        }
    }

    #[DataProvider('invalidStrings')]
    public function test_that_text_rejects_noncanonical_and_unrepresentable_amounts(string $text): void
    {
        $this->expectException(DomainException::class);

        Duration::fromString($text);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidStrings(): iterable
    {
        $max = (string) PHP_INT_MAX;
        $min = (string) PHP_INT_MIN;
        foreach (['', 'us', '1', '1s', '1ms', '1µs', 'PT1S', '+1us', '-0us', '00us', '01us', '-01us',
            '1.0us', '1e3us', '1_000us', '1,000us', ' 1us', '1us ', "1us\n", "1us\0", "1us\r\n",
            '１us', '--1us', '1US', $max.'0us', $min.'0us',
            substr($max, 0, -1).((int) substr($max, -1) + 1).'us',
            substr($min, 0, -1).((int) substr($min, -1) + 1).'us',
            str_repeat('9', 4096).'us', '-'.str_repeat('9', 4096).'us'] as $text) {
            yield [$text];
        }
    }

    #[DataProvider('exactUnits')]
    public function test_that_integer_unit_factories_and_conversions_preserve_exact_amounts(
        string $factory,
        string $conversion,
        int $amount,
        int $microseconds
    ): void {
        $duration = Duration::$factory($amount);

        self::assertSame($microseconds, $duration->toMicroseconds());
        self::assertSame($amount, $duration->$conversion());
        self::assertTrue($duration->equals(Duration::fromMicroseconds($microseconds)));
        self::assertSame($amount, Duration::fromString($microseconds.'us')->$conversion());
    }

    /**
     * @return iterable<array{string, string, int, int}>
     */
    public static function exactUnits(): iterable
    {
        foreach ([['fromMilliseconds', 'toMilliseconds', 1000], ['fromSeconds', 'toSeconds', 1000000]] as $unit) {
            [$factory, $conversion, $factor] = $unit;
            foreach ([intdiv(PHP_INT_MIN, $factor), -2, -1, 0, 1, 2, intdiv(PHP_INT_MAX, $factor)] as $amount) {
                yield [$factory, $conversion, $amount, $amount * $factor];
            }
        }
    }

    public function test_that_seconds_and_milliseconds_share_the_same_exact_microsecond_identity(): void
    {
        self::assertTrue(Duration::fromSeconds(-2)->equals(Duration::fromMilliseconds(-2000)));
        self::assertSame(2000, Duration::fromSeconds(2)->toMilliseconds());
        self::assertSame(-2, Duration::fromMilliseconds(-2000)->toSeconds());
        self::assertSame('1us', Duration::fromMicroseconds(1)->toString());
    }

    #[DataProvider('scaledOverflow')]
    public function test_that_scaling_rejects_overflow_before_multiplication(string $factory, int $amount): void
    {
        $this->expectException(DomainException::class);

        Duration::$factory($amount);
    }

    /**
     * @return iterable<array{string, int}>
     */
    public static function scaledOverflow(): iterable
    {
        foreach ([['fromMilliseconds', 1000], ['fromSeconds', 1000000]] as $unit) {
            [$factory, $factor] = $unit;
            foreach ([PHP_INT_MIN, intdiv(PHP_INT_MIN, $factor) - 1,
                intdiv(PHP_INT_MAX, $factor) + 1, PHP_INT_MAX] as $amount) {
                yield [$factory, $amount];
            }
        }
    }

    #[DataProvider('fractionalUnits')]
    public function test_that_exact_conversion_rejects_positive_and_negative_fractional_units(
        string $conversion,
        int $microseconds
    ): void {
        $this->expectException(DomainException::class);

        Duration::fromMicroseconds($microseconds)->$conversion();
    }

    /**
     * @return iterable<array{string, int}>
     */
    public static function fractionalUnits(): iterable
    {
        foreach (['toMilliseconds', 'toSeconds'] as $conversion) {
            foreach ([PHP_INT_MIN, PHP_INT_MAX, -1000001, -999, -1, 1, 999, 1000001] as $amount) {
                yield [$conversion, $amount];
            }
        }
        yield ['toSeconds', -1000];
        yield ['toSeconds', 1000];
    }

    #[DataProvider('exactArithmetic')]
    public function test_that_checked_arithmetic_returns_new_values_and_retains_both_operands(
        string $operation,
        int $left,
        int $right,
        int $expected
    ): void {
        $duration = Duration::fromMicroseconds($left);
        $other = Duration::fromMicroseconds($right);
        $result = $duration->$operation($other);

        self::assertSame($expected, $result->toMicroseconds());
        self::assertTrue($result->equals(Duration::fromMicroseconds($expected)));
        self::assertNotSame($duration, $result);
        self::assertNotSame($other, $result);
        self::assertSame($left, $duration->toMicroseconds());
        self::assertSame($right, $other->toMicroseconds());
    }

    /**
     * @return iterable<array{string, int, int, int}>
     */
    public static function exactArithmetic(): iterable
    {
        yield ['add', 2, 3, 5];
        yield ['add', -2, -3, -5];
        yield ['add', -7, 3, -4];
        yield ['add', 7, -3, 4];
        yield ['add', PHP_INT_MAX - 1, 1, PHP_INT_MAX];
        yield ['add', PHP_INT_MIN + 1, -1, PHP_INT_MIN];
        yield ['add', PHP_INT_MAX, PHP_INT_MIN, -1];
        yield ['add', PHP_INT_MIN, PHP_INT_MAX, -1];
        yield ['add', PHP_INT_MAX, -PHP_INT_MAX, 0];
        yield ['add', PHP_INT_MIN, 0, PHP_INT_MIN];
        yield ['add', 0, PHP_INT_MAX, PHP_INT_MAX];
        yield ['add', 0, PHP_INT_MIN, PHP_INT_MIN];
        yield ['subtract', 2, 3, -1];
        yield ['subtract', -2, -3, 1];
        yield ['subtract', -7, 3, -10];
        yield ['subtract', 7, -3, 10];
        yield ['subtract', PHP_INT_MAX - 1, -1, PHP_INT_MAX];
        yield ['subtract', PHP_INT_MIN + 1, 1, PHP_INT_MIN];
        yield ['subtract', PHP_INT_MIN, PHP_INT_MIN, 0];
        yield ['subtract', PHP_INT_MAX, PHP_INT_MAX, 0];
        yield ['subtract', -1, PHP_INT_MIN, PHP_INT_MAX];
        yield ['subtract', -1, PHP_INT_MAX, PHP_INT_MIN];
        yield ['subtract', 0, PHP_INT_MAX, -PHP_INT_MAX];
        yield ['subtract', PHP_INT_MIN, 0, PHP_INT_MIN];
        yield ['subtract', PHP_INT_MAX, 0, PHP_INT_MAX];
    }

    #[DataProvider('arithmeticOverflow')]
    public function test_that_arithmetic_overflow_rejects_without_changing_operands(
        string $operation,
        int $left,
        int $right
    ): void {
        $duration = Duration::fromMicroseconds($left);
        $other = Duration::fromMicroseconds($right);
        try {
            $duration->$operation($other);
            self::fail('Unrepresentable arithmetic must reject');
        } catch (DomainException) {
            self::assertSame($left, $duration->toMicroseconds());
            self::assertSame($right, $other->toMicroseconds());
        }
    }

    /**
     * @return iterable<array{string, int, int}>
     */
    public static function arithmeticOverflow(): iterable
    {
        yield ['add', PHP_INT_MAX, 1];
        yield ['add', PHP_INT_MIN, -1];
        yield ['add', PHP_INT_MAX, PHP_INT_MAX];
        yield ['add', PHP_INT_MIN, PHP_INT_MIN];
        yield ['subtract', PHP_INT_MAX, -1];
        yield ['subtract', PHP_INT_MIN, 1];
        yield ['subtract', 0, PHP_INT_MIN];
        yield ['subtract', PHP_INT_MIN, PHP_INT_MAX];
        yield ['subtract', PHP_INT_MAX, PHP_INT_MIN];
    }

    public function test_that_negation_preserves_exact_sign_and_rejects_only_unrepresentable_minimum(): void
    {
        foreach ([PHP_INT_MIN + 1, -1, 0, 1, PHP_INT_MAX] as $amount) {
            $duration = Duration::fromMicroseconds($amount);
            $negated = $duration->negate();
            self::assertNotSame($duration, $negated);
            self::assertSame(-$amount, $negated->toMicroseconds());
            self::assertTrue($duration->equals($negated->negate()));
            self::assertSame($amount, $duration->toMicroseconds());
        }
        $minimum = Duration::fromMicroseconds(PHP_INT_MIN);
        try {
            $minimum->negate();
            self::fail('The positive magnitude of PHP_INT_MIN is not representable');
        } catch (DomainException) {
            self::assertSame(PHP_INT_MIN, $minimum->toMicroseconds());
        }
    }

    public function test_that_ordering_is_numeric_not_lexical_and_coherent_with_equality(): void
    {
        $amounts = [PHP_INT_MIN, -100, -10, -2, -1, 0, 1, 2, 10, 100, PHP_INT_MAX];
        foreach ($amounts as $index => $amount) {
            $duration = Duration::fromMicroseconds($amount);
            foreach ($amounts as $otherIndex => $otherAmount) {
                $other = Duration::fromMicroseconds($otherAmount);
                self::assertSame($index <=> $otherIndex, $duration->compareTo($other));
                self::assertSame($index === $otherIndex, $duration->equals($other));
            }
        }
        $set = HashSet::of(Duration::class);
        $set->add(Duration::fromSeconds(1));
        $set->add(Duration::fromMicroseconds(1000000));
        $set->add(Duration::fromMicroseconds(-1));
        self::assertSame(2, $set->count());
        self::assertTrue($set->contains(Duration::fromMilliseconds(1000)));
        $set->remove(Duration::fromString('1000000us'));
        self::assertSame(1, $set->count());
    }

    #[DataProvider('wrongTypes')]
    public function test_that_comparison_rejects_non_duration_values(mixed $other): void
    {
        $this->expectException(DomainException::class);

        Duration::fromMicroseconds(0)->compareTo($other);
    }

    /**
     * @return iterable<array{mixed}>
     */
    public static function wrongTypes(): iterable
    {
        yield [null];
        yield [0];
        yield ['0us'];
        yield [StringObject::fromString('0us')];
        yield [Time::fromParts(0, 0, 0)];
    }

    #[DataProvider('integerFactories')]
    public function test_that_strict_integer_factories_do_not_accept_float_approximation(string $factory): void
    {
        $this->expectException(TypeError::class);

        Duration::$factory(1.5);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function integerFactories(): iterable
    {
        yield ['fromMicroseconds'];
        yield ['fromMilliseconds'];
        yield ['fromSeconds'];
    }

    public function test_that_native_precision_settings_do_not_change_representation_or_identity(): void
    {
        $precision = ini_get('precision');
        $serializePrecision = ini_get('serialize_precision');
        $duration = Duration::fromMicroseconds(PHP_INT_MAX);
        try {
            ini_set('precision', '3');
            ini_set('serialize_precision', '3');
            self::assertSame(PHP_INT_MAX.'us', $duration->toString());
            self::assertSame('"'.PHP_INT_MAX.'us"', json_encode($duration, JSON_THROW_ON_ERROR));
            self::assertTrue($duration->equals(Duration::fromString($duration->toString())));
            self::assertSame(PHP_INT_MAX.'us', $duration->hashValue());
        } finally {
            ini_set('precision', $precision);
            ini_set('serialize_precision', $serializePrecision);
        }
    }

    public function test_that_duration_cannot_acquire_mutable_properties(): void
    {
        $duration = Duration::fromMicroseconds(-1);
        try {
            $duration->context = 'changed';
            self::fail('Readonly values must reject mutable properties');
        } catch (Error) {
            self::assertSame('-1us', $duration->toString());
        }
    }
}
