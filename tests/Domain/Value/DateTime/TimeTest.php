<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\DateTime;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Error;
use Fight\Common\Domain\Collection\HashSet;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Comparable;
use Fight\Common\Domain\Value\Basic\StringObject;
use Fight\Common\Domain\Value\DateTime\Date;
use Fight\Common\Domain\Value\DateTime\Time;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Time::class)]
class TimeTest extends UnitTestCase
{
    #[DataProvider('validTimes')]
    public function test_that_integer_components_preserve_exact_six_digit_time(
        int $hour,
        int $minute,
        int $second,
        int $microsecond,
        string $text
    ): void {
        $time = Time::fromParts($hour, $minute, $second, $microsecond);

        self::assertInstanceOf(Comparable::class, $time);
        self::assertSame([$hour, $minute, $second, $microsecond], [
            $time->hour(), $time->minute(), $time->second(), $time->microsecond()
        ]);
        self::assertSame($text, $time->toString());
        self::assertSame($text, (string) $time);
        self::assertSame($text, $time->hashValue());
        self::assertTrue($time->equals(Time::fromString($text)));
        self::assertSame(json_encode($text, JSON_THROW_ON_ERROR), json_encode($time, JSON_THROW_ON_ERROR));
        self::assertTrue($time->equals(Time::fromString(json_decode(
            json_encode($time, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR
        ))));
    }

    /**
     * @return iterable<array{int, int, int, int, string}>
     */
    public static function validTimes(): iterable
    {
        yield [0, 0, 0, 0, '00:00:00.000000'];
        yield [0, 0, 0, 1, '00:00:00.000001'];
        yield [1, 2, 3, 45, '01:02:03.000045'];
        yield [12, 34, 56, 100000, '12:34:56.100000'];
        yield [23, 59, 59, 999999, '23:59:59.999999'];
    }

    public function test_that_omitted_microseconds_mean_exact_zero(): void
    {
        self::assertSame('01:02:03.000000', Time::fromParts(1, 2, 3)->toString());
    }

    #[DataProvider('invalidParts')]
    public function test_that_unsupported_components_reject_without_normalization(
        int $hour,
        int $minute,
        int $second,
        int $microsecond
    ): void {
        $this->expectException(DomainException::class);

        Time::fromParts($hour, $minute, $second, $microsecond);
    }

    /**
     * @return iterable<array{int, int, int, int}>
     */
    public static function invalidParts(): iterable
    {
        foreach ([[-1, 0, 0, 0], [24, 0, 0, 0], [PHP_INT_MAX, 0, 0, 0], [PHP_INT_MIN, 0, 0, 0],
            [0, -1, 0, 0], [0, 60, 0, 0], [0, PHP_INT_MAX, 0, 0], [0, 0, -1, 0], [0, 0, 60, 0],
            [0, 0, PHP_INT_MAX, 0], [0, 0, 0, -1], [0, 0, 0, 1000000], [0, 0, 0, PHP_INT_MAX]] as $parts) {
            yield $parts;
        }
    }

    #[DataProvider('invalidStrings')]
    public function test_that_text_requires_canonical_time_without_parser_rollover(string $text): void
    {
        $this->expectException(DomainException::class);

        Time::fromString($text);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidStrings(): iterable
    {
        foreach (['', 'noon', '12:34:56', '12:34:56.1', '12:34:56.12345', '12:34:56.1234567',
            '1:02:03.000000', '01:2:03.000000', '01:02:3.000000', '24:00:00.000000', '23:59:60.000000',
            '00:60:00.000000', '00:00:00.-00001', '00:00:00,000001', '00:00:00.000001Z',
            '00:00:00.000001+01:00', ' 00:00:00.000001', '00:00:00.000001 ', "00:00:00.000001\n",
            "00:00:00.000001\0", '００:00:00.000001', "00:00:00.00000\xff", str_repeat('1', 4096)] as $text) {
            yield [$text];
        }
    }

    public function test_that_equality_hash_and_chronological_order_agree_for_all_components(): void
    {
        $texts = ['00:00:00.000000', '00:00:00.000001', '00:00:01.000000', '00:01:00.000000',
            '01:00:00.000000', '23:59:59.999999'];
        foreach ($texts as $index => $text) {
            $time = Time::fromString($text);
            $equal = Time::fromString($text);
            self::assertNotSame($time, $equal);
            self::assertTrue($time->equals($time));
            self::assertTrue($time->equals($equal));
            self::assertSame($time->hashValue(), $equal->hashValue());
            self::assertFalse($time->equals(StringObject::fromString($text)));
            self::assertFalse($time->equals($text));
            self::assertFalse($time->equals(Date::fromParts(2024, 1, 1)));
            foreach ($texts as $otherIndex => $otherText) {
                $other = Time::fromString($otherText);
                self::assertSame($index <=> $otherIndex, $time->compareTo($other));
                self::assertSame($index === $otherIndex, $time->equals($other));
            }
        }

        $set = HashSet::of(Time::class);
        $set->add(Time::fromParts(1, 2, 3, 4));
        $set->add(Time::fromString('01:02:03.000004'));
        self::assertSame(1, $set->count());
        self::assertTrue($set->contains(Time::fromString('01:02:03.000004')));
    }

    #[DataProvider('wrongTypes')]
    public function test_that_comparison_rejects_other_types(mixed $other): void
    {
        $this->expectException(DomainException::class);

        Time::fromParts(0, 0, 0)->compareTo($other);
    }

    /**
     * @return iterable<array{mixed}>
     */
    public static function wrongTypes(): iterable
    {
        yield [null];
        yield ['00:00:00.000000'];
        yield [StringObject::fromString('00:00:00.000000')];
        yield [Date::fromParts(2024, 1, 1)];
        yield [new DateTimeImmutable('2024-01-01T00:00:00Z')];
    }

    public function test_that_native_extraction_copies_local_time_and_microseconds_without_ambient_conversion(): void
    {
        $originalZone = date_default_timezone_get();
        $native = new DateTime('1969-12-31 23:59:59.999999', new DateTimeZone('-03:30'));
        try {
            date_default_timezone_set('Asia/Tokyo');
            $time = Time::fromNative($native);
            self::assertSame('23:59:59.999999', $time->toString());
            self::assertSame('03:29:59.999999', Time::fromNative(
                DateTimeImmutable::createFromMutable($native)->setTimezone(new DateTimeZone('UTC'))
            )->toString());
            date_default_timezone_set('America/Los_Angeles');
            self::assertTrue($time->equals(Time::fromNative(DateTimeImmutable::createFromMutable($native))));
            $native->modify('+1 second')->setTimezone(new DateTimeZone('UTC'));
            self::assertSame('23:59:59.999999', $time->toString());
            self::assertSame(999999, $time->microsecond());
        } finally {
            date_default_timezone_set($originalZone);
        }
    }

    public function test_that_native_subsecond_value_is_not_rounded_or_lost(): void
    {
        $native = new DateTimeImmutable('1969-12-31T23:59:59.000001Z');

        self::assertSame('23:59:59.000001', Time::fromNative($native)->toString());
    }

    public function test_that_time_cannot_acquire_mutable_properties(): void
    {
        $time = Time::fromParts(1, 2, 3, 4);
        try {
            $time->context = 'changed';
            self::fail('Readonly values must reject mutable properties');
        } catch (Error) {
            self::assertSame('01:02:03.000004', $time->toString());
        }
    }
}
