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
use Fight\Common\Domain\Value\DateTime\WeekDay;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Date::class)]
class DateTest extends UnitTestCase
{
    #[DataProvider('validDates')]
    public function test_that_calendar_components_and_representations_round_trip(
        int $year,
        int $month,
        int $day,
        string $text
    ): void {
        $date = Date::fromParts($year, $month, $day);

        self::assertInstanceOf(Comparable::class, $date);
        self::assertSame([$year, $month, $day], [$date->year(), $date->month(), $date->day()]);
        self::assertSame($text, $date->toString());
        self::assertSame($text, (string) $date);
        self::assertSame($text, $date->hashValue());
        self::assertTrue($date->equals(Date::fromString($text)));
        self::assertSame(json_encode($text, JSON_THROW_ON_ERROR), json_encode($date, JSON_THROW_ON_ERROR));
        self::assertTrue($date->equals(Date::fromString(json_decode(
            json_encode($date, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR
        ))));
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function validDates(): iterable
    {
        yield 'lower year' => [1, 1, 1, '0001-01-01'];
        yield 'upper year' => [9999, 12, 31, '9999-12-31'];
        yield 'Gregorian leap before adoption' => [1200, 2, 29, '1200-02-29'];
        yield 'Gregorian common century before adoption' => [1500, 2, 28, '1500-02-28'];
        yield 'common century' => [1900, 2, 28, '1900-02-28'];
        yield 'leap century' => [2000, 2, 29, '2000-02-29'];
        yield 'ordinary leap year' => [2024, 2, 29, '2024-02-29'];
        yield 'thirty day month' => [2024, 4, 30, '2024-04-30'];
    }

    #[DataProvider('invalidParts')]
    public function test_that_invalid_calendar_components_reject_without_rollover(int $year, int $month, int $day): void
    {
        $this->expectException(DomainException::class);

        Date::fromParts($year, $month, $day);
    }

    /**
     * @return iterable<array{int, int, int}>
     */
    public static function invalidParts(): iterable
    {
        foreach ([[0, 1, 1], [-1, 1, 1], [10000, 1, 1], [PHP_INT_MAX, 1, 1], [PHP_INT_MIN, 1, 1],
            [2024, 0, 1], [2024, 13, 1], [2024, -1, 1], [2024, PHP_INT_MAX, 1],
            [2024, 1, 0], [2024, 1, -1], [2024, 1, 32], [2024, 4, 31], [2024, 2, 30],
            [2023, 2, 29], [1500, 2, 29], [1900, 2, 29], [2100, 2, 29], [2024, 1, PHP_INT_MAX]] as $parts) {
            yield $parts;
        }
    }

    #[DataProvider('invalidStrings')]
    public function test_that_invalid_text_rejects_instead_of_using_native_parser(string $text): void
    {
        $this->expectException(DomainException::class);

        Date::fromString($text);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidStrings(): iterable
    {
        foreach (['', 'today', '2024-2-29', '24-02-29', '+2024-02-29', '10000-01-01', '0000-01-01',
            '2023-02-29', '2024-04-31', '2024-13-01', '2024-00-01', '2024-01-00', '2024/02/29',
            ' 2024-02-29', '2024-02-29 ', "2024-02-29\n", "2024-02-29\0", '２０２４-02-29',
            '2024-02-29T00:00:00Z', "2024-02-\xff", str_repeat('1', 4096)] as $text) {
            yield [$text];
        }
    }

    #[DataProvider('weekdays')]
    public function test_that_weekday_uses_proleptic_gregorian_calendar(string $text, WeekDay $weekday): void
    {
        self::assertSame($weekday, Date::fromString($text)->weekDay());
    }

    /**
     * @return iterable<array{string, WeekDay}>
     */
    public static function weekdays(): iterable
    {
        yield ['0001-01-01', WeekDay::MONDAY];
        yield ['1582-10-04', WeekDay::MONDAY];
        yield ['1970-01-01', WeekDay::THURSDAY];
        yield ['1900-03-01', WeekDay::THURSDAY];
        yield ['2000-03-01', WeekDay::WEDNESDAY];
        yield ['2024-02-26', WeekDay::MONDAY];
        yield ['2024-02-27', WeekDay::TUESDAY];
        yield ['2024-02-28', WeekDay::WEDNESDAY];
        yield ['2024-02-29', WeekDay::THURSDAY];
        yield ['2024-03-01', WeekDay::FRIDAY];
        yield ['2024-03-02', WeekDay::SATURDAY];
        yield ['2024-03-03', WeekDay::SUNDAY];
        yield ['9999-12-31', WeekDay::FRIDAY];
    }

    public function test_that_equality_hash_and_order_agree_across_calendar_boundaries(): void
    {
        $texts = ['0001-01-01', '1900-02-28', '1900-03-01', '2000-02-29', '2024-12-31', '2025-01-01', '9999-12-31'];
        foreach ($texts as $index => $text) {
            $date = Date::fromString($text);
            $equal = Date::fromString($text);
            self::assertNotSame($date, $equal);
            self::assertTrue($date->equals($date));
            self::assertTrue($date->equals($equal));
            self::assertSame($date->hashValue(), $equal->hashValue());
            self::assertFalse($date->equals(StringObject::fromString($text)));
            self::assertFalse($date->equals($text));
            self::assertFalse($date->equals(Time::fromParts(0, 0, 0)));
            foreach ($texts as $otherIndex => $otherText) {
                $other = Date::fromString($otherText);
                self::assertSame($index <=> $otherIndex, $date->compareTo($other));
                self::assertSame($index === $otherIndex, $date->equals($other));
            }
        }

        $set = HashSet::of(Date::class);
        $set->add(Date::fromParts(2024, 2, 29));
        $set->add(Date::fromString('2024-02-29'));
        self::assertSame(1, $set->count());
        self::assertTrue($set->contains(Date::fromString('2024-02-29')));
    }

    #[DataProvider('wrongTypes')]
    public function test_that_comparison_rejects_other_types(mixed $other): void
    {
        $this->expectException(DomainException::class);

        Date::fromString('2024-02-29')->compareTo($other);
    }

    /**
     * @return iterable<array{mixed}>
     */
    public static function wrongTypes(): iterable
    {
        yield [null];
        yield ['2024-02-29'];
        yield [StringObject::fromString('2024-02-29')];
        yield [Time::fromParts(0, 0, 0)];
        yield [new DateTimeImmutable('2024-02-29T00:00:00Z')];
    }

    public function test_that_native_extraction_copies_local_components_not_ambient_timezone(): void
    {
        $originalZone = date_default_timezone_get();
        $native = new DateTime('2024-03-01 00:15:00.000001', new DateTimeZone('+14:00'));
        try {
            date_default_timezone_set('America/Los_Angeles');
            $date = Date::fromNative($native);
            self::assertSame('2024-03-01', $date->toString());
            self::assertSame('2024-02-29', Date::fromNative(
                DateTimeImmutable::createFromMutable($native)->setTimezone(new DateTimeZone('UTC'))
            )->toString());
            date_default_timezone_set('Asia/Tokyo');
            self::assertTrue($date->equals(Date::fromNative(DateTimeImmutable::createFromMutable($native))));
            $native->modify('+1 year')->setTimezone(new DateTimeZone('UTC'));
            self::assertSame('2024-03-01', $date->toString());
            self::assertSame(WeekDay::FRIDAY, $date->weekDay());
        } finally {
            date_default_timezone_set($originalZone);
        }
    }

    #[DataProvider('unsupportedNativeDates')]
    public function test_that_native_extraction_rejects_unsupported_years(string $text): void
    {
        $this->expectException(DomainException::class);

        Date::fromNative(new DateTimeImmutable($text, new DateTimeZone('UTC')));
    }

    /**
     * @return iterable<array{string}>
     */
    public static function unsupportedNativeDates(): iterable
    {
        yield ['0000-01-01'];
        yield ['-0001-01-01'];
        yield ['+10000-01-01'];
    }

    public function test_that_date_cannot_acquire_mutable_properties(): void
    {
        $date = Date::fromString('2024-02-29');
        try {
            $date->context = 'changed';
            self::fail('Readonly values must reject mutable properties');
        } catch (Error) {
            self::assertSame('2024-02-29', $date->toString());
        }
    }
}
