<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\DateTime;

use DateTime as NativeDateTime;
use DateTimeImmutable;
use DateTimeZone;
use Fight\Common\Domain\Collection\HashSet;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\DateTime\Date;
use Fight\Common\Domain\Value\DateTime\DateTime;
use Fight\Common\Domain\Value\DateTime\Time;
use Fight\Common\Domain\Value\DateTime\Timezone;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DateTime::class)]
class DateTimeTest extends UnitTestCase
{
    public function test_that_local_construction_rejects_gaps_and_ambiguous_folds(): void
    {
        $newYork = Timezone::fromString('America/New_York');
        $gap = Date::fromString('2026-03-08');
        $fold = Date::fromString('2026-11-01');
        $time = Time::fromString('02:30:00.123456');
        foreach ([null, -18000, -14400] as $offset) {
            try {
                DateTime::fromLocal($gap, $time, $newYork, $offset);
                self::fail('The gap cannot identify an occurrence');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }

        $this->expectException(DomainException::class);
        DateTime::fromLocal($fold, Time::fromString('01:30:00.123456'), $newYork);
    }

    public function test_that_non_hour_gaps_and_skipped_dates_reject_even_with_an_offset(): void
    {
        foreach ([
            ['2026-10-04', '02:15:00.000000', 'Australia/Lord_Howe', 37800],
            ['2011-12-30', '12:00:00.000000', 'Pacific/Apia', -36000]
        ] as [$date, $time, $zone, $offset]) {
            try {
                DateTime::fromLocal(Date::fromString($date), Time::fromString($time), Timezone::fromString($zone), $offset);
                self::fail('A skipped local occurrence must reject');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_that_both_folds_round_trip_as_distinct_instants_with_exact_microseconds(): void
    {
        $date = Date::fromString('2026-11-01');
        $time = Time::fromString('01:30:00.123456');
        $zone = Timezone::fromString('America/New_York');
        $first = DateTime::fromLocal($date, $time, $zone, -14400);
        $second = DateTime::fromLocal($date, $time, $zone, -18000);

        self::assertSame('1793511000.123456', $first->toNative()->format('U.u'));
        self::assertSame('1793514600.123456', $second->toNative()->format('U.u'));
        self::assertSame('01:30:00.123456', $second->time()->toString());
        self::assertSame('2026-11-01', $second->date()->toString());
        self::assertSame(-1, $first->compareInstantTo($second));
        self::assertSame(1, $second->compareTo($first));
        foreach ([$first, $second] as $value) {
            $restored = DateTime::fromString($value->toString());
            self::assertTrue($value->equals($restored));
            self::assertSame($value->hashValue(), $restored->hashValue());
            self::assertSame($value->toString(), json_decode(json_encode($value, JSON_THROW_ON_ERROR), true));
            self::assertTrue($value->equals(DateTime::fromNative($value->toNative())));
        }
    }

    #[DataProvider('otherTimezoneCases')]
    public function test_that_historical_and_non_hour_offsets_select_actual_occurrences(
        string $date,
        string $time,
        string $zone,
        int $offset
    ): void {
        $value = DateTime::fromLocal(Date::fromString($date), Time::fromString($time), Timezone::fromString($zone), $offset);
        self::assertSame($offset, $value->toNative()->getOffset());
        self::assertSame($date . ' ' . $time, $value->toNative()->format('Y-m-d H:i:s.u'));
        self::assertTrue($value->equals(DateTime::fromString($value->toString())));
    }

    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function otherTimezoneCases(): iterable
    {
        yield 'historic Paris seconds' => ['1890-01-01', '12:00:00.123456', 'Europe/Paris', 561];
        yield 'Kathmandu quarter hour' => ['2026-01-01', '12:00:00.000001', 'Asia/Kathmandu', 20700];
        yield 'fixed offset' => ['2026-01-01', '12:00:00.000001', '+05:30', 19800];
        yield 'abbreviation' => ['2026-01-01', '12:00:00.000001', 'EST', -18000];
        yield 'Lord Howe overlap' => ['2026-04-05', '01:45:00.000001', 'Australia/Lord_Howe', 37800];
        yield 'Lord Howe second fold' => ['2026-04-05', '01:45:00.000001', 'Australia/Lord_Howe', 39600];
    }

    public function test_that_unique_occurrences_validate_supplied_offsets_and_normalize_native_offset_names(): void
    {
        $date = Date::fromString('2026-01-01');
        $time = Time::fromString('12:00:00.000000');
        $value = DateTime::fromLocal($date, $time, Timezone::fromString('+5:30'), 19800);
        self::assertSame('+05:30', $value->timezone()->value());
        self::assertTrue($value->equals(DateTime::fromLocal($date, $time, Timezone::fromString('+05:30'))));
        self::assertSame(0, $value->compareTo(DateTime::fromString($value->toString())));

        $this->expectException(DomainException::class);
        DateTime::fromLocal($date, $time, Timezone::fromString('+05:30'), 19801);
    }

    public function test_that_native_mutation_does_not_change_value_and_pre_epoch_fraction_is_not_a_decimal(): void
    {
        $native = new NativeDateTime('1969-12-31 23:59:59.500000', new DateTimeZone('UTC'));
        $value = DateTime::fromNative($native);
        $native->modify('+1 year');
        self::assertSame('-1.500000', $value->toNative()->format('U.u'));
        self::assertSame('-1', $value->instantSeconds());
        self::assertTrue($value->equals(DateTime::fromString($value->toString())));
        self::assertTrue($value->equals(DateTime::fromInstant('-1', 500000, Timezone::fromString('UTC'))));
        self::assertSame('-1.500000', $value->toNative()->format('U.u'));
    }

    public function test_that_same_instant_aliases_and_zones_have_distinct_value_identity(): void
    {
        $utc = DateTime::fromInstant('0', 0, Timezone::fromString('UTC'));
        $alias = $utc->inTimezone(Timezone::fromString('Etc/UTC'));
        $newYork = $utc->inTimezone(Timezone::fromString('America/New_York'));
        self::assertFalse($utc->equals($alias));
        self::assertTrue($utc->isSameInstantAs($alias));
        self::assertSame(0, $utc->compareInstantTo($newYork));
        self::assertSame(0, $newYork->compareInstantTo($utc));
        self::assertSame(-1, $alias->compareTo($utc));
        self::assertSame(1, $utc->compareTo($alias));
        self::assertFalse($utc->equals(Time::fromParts(0, 0, 0)));
        self::assertNotSame($utc->hashValue(), $alias->hashValue());
        $set = HashSet::of(DateTime::class);
        $set->add($utc);
        $set->add(DateTime::fromString($utc->toString()));
        $set->add($alias);
        self::assertSame(2, $set->count());
    }

    public function test_that_conversion_preserves_instant_and_reinterpretation_preserves_wall_components(): void
    {
        $utc = DateTime::fromLocal(
            Date::fromString('2026-11-01'), Time::fromString('01:30:00.123456'), Timezone::fromString('UTC')
        );
        $zone = Timezone::fromString('America/New_York');
        $converted = $utc->inTimezone($zone);
        self::assertTrue($utc->isSameInstantAs($converted));
        self::assertSame('2026-10-31 21:30:00.123456', $converted->toNative()->format('Y-m-d H:i:s.u'));
        $reinterpreted = $utc->reinterpretInTimezone($zone, -18000);
        self::assertFalse($utc->isSameInstantAs($reinterpreted));
        self::assertSame('2026-11-01 01:30:00.123456', $reinterpreted->toNative()->format('Y-m-d H:i:s.u'));
        $this->expectException(DomainException::class);
        $utc->reinterpretInTimezone($zone);
    }

    public function test_that_gap_and_unsupported_target_date_reject_during_conversion(): void
    {
        $utc = DateTime::fromLocal(
            Date::fromString('2026-03-08'), Time::fromString('02:30:00.000000'), Timezone::fromString('UTC')
        );
        try {
            $utc->reinterpretInTimezone(Timezone::fromString('America/New_York'), -18000);
            self::fail('Gap must reject');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $lower = DateTime::fromLocal(
            Date::fromString('0001-01-01'), Time::fromString('00:00:00.000000'), Timezone::fromString('UTC')
        );
        $this->expectException(DomainException::class);
        $lower->inTimezone(Timezone::fromString('-01:00'));
    }

    #[DataProvider('badFrames')]
    public function test_that_malformed_or_unsupported_frames_reject(string $frame): void
    {
        $this->expectException(DomainException::class);
        DateTime::fromString($frame);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function badFrames(): iterable
    {
        yield [''];
        yield ['zoned:v1:-0:000000:VVRD'];
        yield ['zoned:v1:0:00000:VVRD'];
        yield ['zoned:v1:0:000000:'];
        yield ['zoned:v1:0:000000:!!!'];
        yield ['zoned:v1:0:000000:VVRD='];
        yield ['zoned:v1:999999999999999999999999999:000000:VVRD'];
        yield ['zoned:v1:0:000000:Tm8vU3VjaA=='];
        yield ['zoned:v1:0:000000:VVRD' . "\n"];
    }

    public function test_that_invalid_coordinates_and_wrong_type_comparison_reject(): void
    {
        $zone = Timezone::fromString('UTC');
        foreach ([['01', 0], ['-0', 0], ['0', -1], ['0', 1000000], [str_repeat('9', 50), 0]] as [$seconds, $micros]) {
            try {
                DateTime::fromInstant($seconds, $micros, $zone);
                self::fail('Invalid coordinate accepted');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }
        $this->expectException(DomainException::class);
        DateTime::fromInstant('0', 0, $zone)->compareTo(new DateTimeImmutable('now'));
    }

    public function test_that_native_out_of_range_dates_reject_without_changing_existing_timestamp_apis(): void
    {
        foreach (['0000-01-01', '+10000-01-01'] as $date) {
            try {
                DateTime::fromNative(new DateTimeImmutable($date, new DateTimeZone('UTC')));
                self::fail('Unsupported native year accepted');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }
        self::assertSame('0001-01-01', DateTime::fromInstant('-62135596800', 0, Timezone::fromString('UTC'))
            ->date()->toString());
        self::assertSame('9999-12-31', DateTime::fromLocal(
            Date::fromString('9999-12-31'), Time::fromString('23:59:59.999999'), Timezone::fromString('UTC')
        )->date()->toString());
    }
}
