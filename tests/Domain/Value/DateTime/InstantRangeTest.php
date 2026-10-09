<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\DateTime;

use DateTimeImmutable;
use Error;
use Fight\Common\Domain\Collection\HashSet;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StringObject;
use Fight\Common\Domain\Value\DateTime\DateTime;
use Fight\Common\Domain\Value\DateTime\InstantRange;
use Fight\Common\Domain\Value\DateTime\Timezone;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(InstantRange::class)]
class InstantRangeTest extends UnitTestCase
{
    public function test_that_membership_is_half_open_at_microsecond_precision_independent_of_zones(): void
    {
        $start = DateTime::fromNative(new DateTimeImmutable('2026-11-01 05:30:00.000001 UTC'));
        $end = DateTime::fromNative(new DateTimeImmutable('2026-11-01 05:30:00.000004 UTC'));
        $range = InstantRange::fromInstants($start->inTimezone(Timezone::fromString('Pacific/Honolulu')), $end);
        $cases = [
            '2026-11-01 05:30:00.000000 UTC' => false,
            '2026-11-01 05:30:00.000001 UTC' => true,
            '2026-11-01 05:30:00.000002 UTC' => true,
            '2026-11-01 05:30:00.000003 UTC' => true,
            '2026-11-01 05:30:00.000004 UTC' => false,
            '2026-11-01 05:30:00.000005 UTC' => false
        ];

        foreach ($cases as $text => $expected) {
            $candidate = DateTime::fromNative(new DateTimeImmutable($text));
            self::assertSame($expected, $range->contains($candidate), $text);
            self::assertSame(
                $expected,
                $range->contains($candidate->inTimezone(Timezone::fromString('America/New_York'))),
                $text
            );
        }
    }

    public function test_that_zone_identifier_tie_breakers_never_change_membership_or_endpoint_order(): void
    {
        $utc = DateTime::fromNative(new DateTimeImmutable('2024-02-29 12:00:00.123456 UTC'));
        $same = $utc->inTimezone(Timezone::fromString('America/New_York'));
        $later = DateTime::fromNative(new DateTimeImmutable('2024-02-29 12:00:00.123457 UTC'));
        $range = InstantRange::fromInstants($same, $later);

        self::assertFalse($utc->equals($same));
        self::assertSame(0, $utc->compareInstantTo($same));
        self::assertTrue($range->contains($utc));
        self::assertTrue($range->contains($same));
        self::assertFalse($range->contains($later->inTimezone(Timezone::fromString('Pacific/Honolulu'))));
        self::assertSame($same, $range->start());
        self::assertSame($later, $range->end());

        $empty = InstantRange::fromInstants($same, $utc);
        self::assertFalse($empty->contains($same));
        self::assertFalse($empty->contains($utc));
        self::assertFalse($empty->contains($later));
        $before = DateTime::fromNative(new DateTimeImmutable('2024-02-29 12:00:00.123455 UTC'));
        self::assertFalse($empty->contains($before));
        self::assertTrue(InstantRange::fromInstants($utc, $same)->equals($empty));
    }

    public function test_that_reversed_instant_endpoints_reject_even_when_zone_labels_sort_differently(): void
    {
        $later = DateTime::fromNative(new DateTimeImmutable('2024-02-29 12:00:00.000001 UTC'));
        $earlier = DateTime::fromNative(new DateTimeImmutable('2024-02-29 12:00:00.000000 UTC'));
        $this->expectException(DomainException::class);

        InstantRange::fromInstants($later->inTimezone(Timezone::fromString('Pacific/Honolulu')), $earlier);
    }

    public function test_that_supported_extremes_reconstruct_and_end_remains_exclusive(): void
    {
        $start = DateTime::fromNative(new DateTimeImmutable('0001-01-01 00:00:00.000000 UTC'));
        $last = DateTime::fromNative(new DateTimeImmutable('9999-12-31 23:59:59.999998 UTC'));
        $end = DateTime::fromNative(new DateTimeImmutable('9999-12-31 23:59:59.999999 UTC'));
        $range = InstantRange::fromInstants($start, $end);
        $copy = InstantRange::fromString($range->toString());

        self::assertTrue($copy->contains($start));
        self::assertTrue($copy->contains($last));
        self::assertFalse($copy->contains($end));
        self::assertSame($start->instantSeconds(), $copy->start()->instantSeconds());
        self::assertSame($end->toNative()->format('U.u'), $copy->end()->toNative()->format('U.u'));
    }

    public function test_that_boundary_instants_outside_utc_calendar_years_still_round_trip(): void
    {
        $start = DateTime::fromNative(new DateTimeImmutable('0001-01-01 00:00:00.000001 +14:00'));
        $end = DateTime::fromNative(new DateTimeImmutable('9999-12-31 23:59:59.999999 -12:00'));
        $range = InstantRange::fromInstants($start, $end);
        $copy = InstantRange::fromString($range->toString());

        self::assertTrue($copy->equals($range));
        self::assertSame($start->toString(), $copy->start()->toString());
        self::assertSame($end->toString(), $copy->end()->toString());
        self::assertTrue($copy->contains($start));
        self::assertFalse($copy->contains($end));
    }

    public function test_that_identity_and_json_use_instant_pairs_not_zone_context(): void
    {
        $start = DateTime::fromNative(new DateTimeImmutable('1969-12-31 23:59:59.500000 UTC'));
        $end = DateTime::fromNative(new DateTimeImmutable('1970-01-01 00:00:00.000001 UTC'));
        $range = InstantRange::fromInstants($start, $end);
        $zones = InstantRange::fromInstants(
            $start->inTimezone(Timezone::fromString('America/New_York')),
            $end->inTimezone(Timezone::fromString('Pacific/Honolulu'))
        );
        $text = 'instant-range:v1:'.base64_encode($start->toString()).':'.base64_encode($end->toString());
        $copy = InstantRange::fromString($text);

        self::assertSame($text, $range->toString());
        self::assertSame($zones->toString(), (string) $zones);
        self::assertNotSame($text, $zones->toString());
        self::assertSame('instant-range:v1:-1:500000:0:000001', $zones->hashValue());
        self::assertSame($range->hashValue(), $zones->hashValue());
        self::assertSame(json_encode($text, JSON_THROW_ON_ERROR), json_encode($range, JSON_THROW_ON_ERROR));
        self::assertTrue($range->equals($range));
        self::assertTrue($range->equals($zones));
        self::assertTrue($range->equals($copy));
        self::assertSame('UTC', $copy->start()->timezone()->value());
        self::assertSame('America/New_York', $zones->start()->timezone()->value());
        self::assertSame('America/New_York', InstantRange::fromString($zones->toString())->start()->timezone()->value());
        self::assertTrue($copy->contains($start));
        self::assertFalse($copy->contains($end));
        $decoded = json_decode(json_encode($zones, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($range->equals(InstantRange::fromString($decoded)));
        self::assertFalse($range->equals(InstantRange::fromInstants($start, $start)));
        self::assertFalse($range->equals(InstantRange::fromInstants($end, $end)));
        self::assertFalse(InstantRange::fromInstants($start, $start)->equals(InstantRange::fromInstants($end, $end)));
        self::assertNotSame(
            InstantRange::fromInstants($start, $start)->hashValue(),
            InstantRange::fromInstants($end, $end)->hashValue()
        );
        $differentEnd = DateTime::fromNative(new DateTimeImmutable('1970-01-01 00:00:00.000002 UTC'));
        self::assertFalse($range->equals(InstantRange::fromInstants($start, $differentEnd)));
        self::assertFalse($range->equals(StringObject::fromString($text)));
        self::assertFalse($range->equals($text));
        self::assertFalse($range->equals(null));

        $set = HashSet::of(InstantRange::class);
        $set->add($range);
        $set->add($zones);
        self::assertSame(1, $set->count());
    }

    #[DataProvider('invalidRepresentations')]
    public function test_that_invalid_frames_coordinates_and_order_reject(string $text): void
    {
        $this->expectException(DomainException::class);

        InstantRange::fromString($text);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidRepresentations(): iterable
    {
        $start = DateTime::fromInstant('0', 0, Timezone::fromString('UTC'))->toString();
        $end = DateTime::fromInstant('1', 0, Timezone::fromString('UTC'))->toString();
        $first = base64_encode($start);
        $last = base64_encode($end);
        $invalid = [
            '',
            'instant-range:v2:'.$first.':'.$last,
            'instant-range:v1:'.$last.':'.$first,
            'instant-range:v1:'.$first.':',
            'instant-range:v1:'.$first.':'.base64_encode('zoned:v2:1:000000:VVRD'),
            'instant-range:v1:'.$first.':'.base64_encode('zoned:v1:1:1000000:VVRD'),
            'instant-range:v1:'.$first.':'.base64_encode('zoned:v1:253402300800:000000:VVRD'),
            'instant-range:v1:'.$first.':'.base64_encode('zoned:v1:-62135596801:000000:VVRD'),
            'instant-range:v1:'.$first.':'.base64_encode('zoned:v1:1:000000:YmFkL3pvbmU='),
            'instant-range:v1:'.$first.':'.base64_encode('zoned:v1:1:000000:VVRD').'=',
            'instant-range:v1:'.$first.':'.base64_encode('not a date'),
            'instant-range:v1:'.$first.':'.$last.':extra',
            "instant-range:v1:{$first}:{$last}\n",
            "instant-range:v1:{$first}:{$last}\0"
        ];

        foreach ($invalid as $text) {
            yield [$text];
        }
    }

    public function test_that_readonly_range_rejects_mutation_without_changing_endpoints(): void
    {
        $start = DateTime::fromNative(new DateTimeImmutable('2024-01-01 UTC'));
        $range = InstantRange::fromInstants($start, $start);
        try {
            $range->start = DateTime::fromNative(new DateTimeImmutable('2025-01-01 UTC'));
            self::fail('Readonly values must reject changed endpoints');
        } catch (Error) {
            self::assertSame($start, $range->start());
            self::assertSame($start, $range->end());
        }
    }
}
