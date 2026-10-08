<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\DateTime;

use Error;
use Fight\Common\Domain\Collection\HashSet;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StringObject;
use Fight\Common\Domain\Value\DateTime\Date;
use Fight\Common\Domain\Value\DateTime\DateRange;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DateRange::class)]
class DateRangeTest extends UnitTestCase
{
    #[DataProvider('calendarIntervals')]
    public function test_that_membership_includes_exact_endpoints_and_interior_without_steps(
        string $start,
        string $end,
        string $before,
        string $interior,
        string $after
    ): void {
        $range = DateRange::fromDates(Date::fromString($start), Date::fromString($end));

        $cases = [$before => false, $start => true, $interior => true, $end => true, $after => false];
        foreach ($cases as $text => $expected) {
            self::assertSame($expected, $range->contains(Date::fromString($text)), $text);
        }
    }

    /**
     * @return iterable<array{string, string, string, string, string}>
     */
    public static function calendarIntervals(): iterable
    {
        yield 'leap day and month' => ['2024-02-28', '2024-03-01', '2024-02-27', '2024-02-29', '2024-03-02'];
        yield 'year crossing' => ['2024-12-31', '2025-01-02', '2024-12-30', '2025-01-01', '2025-01-03'];
        yield 'earliest years' => ['0001-01-01', '0001-01-03', '0001-01-02', '0001-01-02', '0001-01-04'];
        yield 'late years' => ['9998-12-31', '9999-01-02', '9998-12-30', '9999-01-01', '9999-01-03'];
    }

    public function test_that_full_supported_calendar_bounds_are_inclusive(): void
    {
        $range = DateRange::fromDates(Date::fromString('0001-01-01'), Date::fromString('9999-12-31'));

        self::assertTrue($range->contains(Date::fromString('0001-01-01')));
        self::assertTrue($range->contains(Date::fromString('9999-12-31')));
    }

    public function test_that_equal_endpoints_form_one_date_range(): void
    {
        $date = Date::fromString('2024-02-29');
        $range = DateRange::fromDates($date, $date);

        self::assertSame($date, $range->start());
        self::assertSame($date, $range->end());
        self::assertTrue($range->contains($date));
        self::assertFalse($range->contains(Date::fromString('2024-02-28')));
        self::assertFalse($range->contains(Date::fromString('2024-03-01')));
    }

    #[DataProvider('reversedIntervals')]
    public function test_that_reversed_endpoints_reject_instead_of_swapping(string $start, string $end): void
    {
        $this->expectException(DomainException::class);

        DateRange::fromDates(Date::fromString($start), Date::fromString($end));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function reversedIntervals(): iterable
    {
        yield ['2024-03-01', '2024-02-29'];
        yield ['9999-12-31', '0001-01-01'];
    }

    public function test_that_endpoint_identity_hash_and_json_reconstruct_without_aliases(): void
    {
        $start = Date::fromString('0001-01-01');
        $end = Date::fromString('9999-12-31');
        $range = DateRange::fromDates($start, $end);
        $text = 'date-range:v1:0001-01-01:9999-12-31';
        $reconstructed = DateRange::fromString($text);

        self::assertSame($start, $range->start());
        self::assertSame($end, $range->end());
        self::assertSame($text, $range->toString());
        self::assertSame($text, (string) $range);
        self::assertSame($text, $range->hashValue());
        self::assertSame(json_encode($text, JSON_THROW_ON_ERROR), json_encode($range, JSON_THROW_ON_ERROR));
        self::assertNotSame($range, $reconstructed);
        self::assertTrue($range->equals($range));
        self::assertTrue($range->equals($reconstructed));
        self::assertSame($range->hashValue(), $reconstructed->hashValue());
        $decoded = json_decode(json_encode($range, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($range->equals(DateRange::fromString($decoded)));
        self::assertTrue($reconstructed->start()->equals($start));
        self::assertTrue($reconstructed->end()->equals($end));
        self::assertFalse($range->equals(DateRange::fromDates($start, Date::fromString('9999-12-30'))));
        self::assertFalse($range->equals(DateRange::fromDates(Date::fromString('0001-01-02'), $end)));
        self::assertFalse($range->equals(StringObject::fromString($text)));
        self::assertFalse($range->equals($text));
        self::assertFalse($range->equals(null));

        $set = HashSet::of(DateRange::class);
        $set->add($range);
        $set->add($reconstructed);
        self::assertSame(1, $set->count());
    }

    #[DataProvider('invalidRepresentations')]
    public function test_that_malformed_unsupported_or_reversed_representations_reject(string $text): void
    {
        $this->expectException(DomainException::class);

        DateRange::fromString($text);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidRepresentations(): iterable
    {
        foreach (['', 'date-range:v2:2024-02-28:2024-03-01',
            'date-range:v1:2024-03-01:2024-02-29',
            'date-range:v1:0000-01-01:2024-03-01',
            'date-range:v1:2024-02-29:10000-01-01',
            'date-range:v1:2023-02-29:2024-03-01',
            'date-range:v1:2024-02-29:2024-04-31',
            'date-range:v1:2024-2-29:2024-03-01',
            'date-range:v1:2024-02-29/2024-03-01',
            'date-range:v1:2024-02-29:2024-03-01:extra',
            ' date-range:v1:2024-02-29:2024-03-01',
            "date-range:v1:2024-02-29:2024-03-01\n",
            "date-range:v1:2024-02-29:2024-03-01\0"] as $text) {
            yield [$text];
        }
    }

    public function test_that_readonly_range_cannot_acquire_mutable_state(): void
    {
        $range = DateRange::fromDates(Date::fromString('2024-02-29'), Date::fromString('2024-03-01'));
        try {
            $range->context = 'changed';
            self::fail('Readonly values must reject mutable properties');
        } catch (Error) {
            self::assertSame('2024-02-29', $range->start()->toString());
            self::assertSame('2024-03-01', $range->end()->toString());
        }
    }
}
