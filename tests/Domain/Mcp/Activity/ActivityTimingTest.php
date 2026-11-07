<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Mcp\Activity;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Activity\ActivityTiming;
use Fight\Common\Domain\Mcp\Observation\ObservationOccurrence;
use Fight\Common\Domain\Value\DateTime\Duration;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

#[CoversClass(ActivityTiming::class)]
final class ActivityTimingTest extends UnitTestCase
{
    #[DataProvider('knownTiming')]
    public function test_that_timing_preserves_partial_precision_and_independent_elapsed(?string $start, ?string $end, ?int $microseconds): void
    {
        $startedAt = $start === null ? null : ObservationOccurrence::fromString($start);
        $endedAt = $end === null ? null : ObservationOccurrence::fromString($end);
        $elapsed = $microseconds === null ? null : Duration::fromMicroseconds($microseconds);
        $timing = ActivityTiming::fromParts($startedAt, $endedAt, $elapsed);
        $expected = ['started_at' => $start, 'ended_at' => $end, 'elapsed' => $microseconds === null ? null : $microseconds.'us'];
        self::assertSame($startedAt, $timing->startedAt());
        self::assertSame($endedAt, $timing->endedAt());
        self::assertSame($elapsed, $timing->elapsed());
        self::assertSame($expected, $timing->toArray());
        self::assertSame($expected, $timing->jsonSerialize());
        self::assertSame(json_encode($expected, JSON_THROW_ON_ERROR), json_encode($timing, JSON_THROW_ON_ERROR));
        self::assertSame(json_encode($expected, JSON_THROW_ON_ERROR), $timing->toString());
        self::assertSame($timing->toString(), (string) $timing);
        foreach ([ActivityTiming::fromString($timing->toString()), ActivityTiming::fromArray(array_reverse($expected, true))] as $copy) {
            self::assertTrue($timing->equals($copy));
            self::assertSame($timing->hashValue(), $copy->hashValue());
            self::assertSame($expected, $copy->toArray());
        }
        self::assertSame([], get_object_vars($timing));
    }

    public static function knownTiming(): iterable
    {
        yield 'unknown' => [null, null, null];
        yield 'start only' => ['2026-11-06T12:00:00Z', null, null];
        yield 'end only' => [null, '2026-11-06T12:00:01.120Z', null];
        yield 'elapsed only zero' => [null, null, 0];
        yield 'native elapsed maximum' => [null, null, PHP_INT_MAX];
        yield 'independent measurement' => ['2026-11-06T12:00:00.12Z', '2026-11-06T12:00:01.120001Z', 7];
        yield 'equal instants distinct precision' => ['2026-11-06T12:00:00.1Z', '2026-11-06T12:00:00.100000Z', 123];
        foreach (['', '.0', '.12', '.120', '.1200', '.12000', '.120001'] as $fraction) {
            yield 'source precision '.$fraction => ['2026-11-06T12:00:00'.$fraction.'Z', '2026-11-06T12:00:01'.$fraction.'Z', 0];
        }
    }

    public function test_that_timing_equality_retains_precision_and_each_independent_field(): void
    {
        $timing = ActivityTiming::fromParts(ObservationOccurrence::fromString('2026-11-06T12:00:00.1Z'), null, null);
        self::assertFalse($timing->equals(ActivityTiming::fromParts(ObservationOccurrence::fromString('2026-11-06T12:00:00.100Z'), null, null)));
        self::assertFalse($timing->equals(ActivityTiming::fromParts(null, $timing->startedAt(), null)));
        self::assertFalse($timing->equals(ActivityTiming::fromParts($timing->startedAt(), null, Duration::fromMicroseconds(0))));
        self::assertFalse($timing->equals($timing->toArray()));
    }

    public function test_that_timing_copies_input_and_output_arrays(): void
    {
        $start = '2026-11-06T12:00:00.120Z';
        $elapsed = '0us';
        $input = ['elapsed' => &$elapsed, 'ended_at' => null, 'started_at' => &$start];
        $timing = ActivityTiming::fromArray($input);
        $start = 'private sentinel';
        $elapsed = '-1us';
        $output = $timing->toArray();
        $output['started_at'] = null;
        $output['elapsed'] = '999us';
        self::assertSame(['started_at' => '2026-11-06T12:00:00.120Z', 'ended_at' => null, 'elapsed' => '0us'], $timing->toArray());
    }

    public function test_that_timing_rejects_reversed_endpoints_and_negative_elapsed_without_clamping(): void
    {
        $start = ObservationOccurrence::fromString('2026-11-06T12:00:00.120001Z');
        $end = ObservationOccurrence::fromString('2026-11-06T12:00:00.120Z');
        foreach ([[$start, $end, null], [null, null, Duration::fromMicroseconds(-1)], [null, null, Duration::fromMicroseconds(PHP_INT_MIN)]] as [$startedAt, $endedAt, $elapsed]) {
            try {
                ActivityTiming::fromParts($startedAt, $endedAt, $elapsed);
                self::fail('Expected invalid measurement rejection.');
            } catch (DomainException $exception) {
                self::assertSame('Invalid activity timing.', $exception->getMessage());
            }
        }
    }

    #[DataProvider('invalidStructures')]
    public function test_that_timing_requires_exact_nullable_string_fields(array $data): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/\AInvalid activity timing structure\.\z/D');
        ActivityTiming::fromArray($data);
    }

    public static function invalidStructures(): iterable
    {
        $unknown = ['started_at' => null, 'ended_at' => null, 'elapsed' => null];
        foreach (array_keys($unknown) as $field) {
            $missing = $unknown;
            unset($missing[$field]);
            yield 'missing '.$field => [$missing];
            foreach ([0, false, [], new stdClass()] as $invalid) {
                yield [array_replace($unknown, [$field => $invalid])];
            }
        }
        yield 'unknown field' => [$unknown + ['private' => 'sentinel']];
        yield 'list' => [[null, null, null]];
    }

    #[DataProvider('invalidMeasurements')]
    public function test_that_timing_reuses_strict_occurrence_and_duration_rejections(array $changes, string $message): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/\A'.preg_quote($message, '/').'\z/D');
        ActivityTiming::fromArray(array_replace(['started_at' => null, 'ended_at' => null, 'elapsed' => null], $changes));
    }

    public static function invalidMeasurements(): iterable
    {
        yield [['started_at' => 'private sentinel'], 'Invalid observation occurrence.'];
        yield [['ended_at' => '2026-11-06T12:00:00.1234567Z'], 'Invalid observation occurrence.'];
        foreach (['0', '00us', '-0us', '+1us', '1ms', '1.0us', 'private sentinel'] as $elapsed) {
            yield [['elapsed' => $elapsed], 'Invalid duration representation'];
        }
        yield [['elapsed' => '-1us'], 'Invalid activity timing.'];
        yield [['elapsed' => ((string) PHP_INT_MAX).'0us'], 'Duration exceeds the native integer range'];
        yield [['started_at' => '2026-11-06T12:00:01Z', 'ended_at' => '2026-11-06T12:00:00Z'], 'Invalid activity timing.'];
    }

    #[DataProvider('invalidTexts')]
    public function test_that_timing_rejects_malformed_or_scalar_text_with_fixed_error(string $text): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/\AInvalid activity timing text\.\z/D');
        ActivityTiming::fromString($text);
    }

    public static function invalidTexts(): iterable
    {
        foreach (['private sentinel', '{', 'null', 'false', '1', '"private sentinel"'] as $text) {
            yield [$text];
        }
    }
}
