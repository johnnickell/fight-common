<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Execution\Observation;

use DateTimeImmutable;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Activity\PhaseActivity;
use Fight\Common\Domain\Execution\Observation\Command\RecordPhaseObservation;
use Fight\Common\Domain\Execution\Observation\Event\ObservationRecorded;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Observation\ObservationOccurrence;
use Fight\Common\Domain\Execution\Observation\ObservationOutcome;
use Fight\Common\Domain\Execution\Observation\RecordedObservation;
use Fight\Common\Domain\Execution\Usage\UsageCompleteness;
use Fight\Common\Domain\Execution\Usage\UsageMeasurement;
use Fight\Common\Domain\Execution\Value\ObservationFields;
use Fight\Test\Common\TestCase\Execution\ObservationFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(PhaseActivity::class)]
#[CoversClass(UsageMeasurement::class)]
#[CoversClass(UsageCompleteness::class)]
#[CoversClass(ObservationOccurrence::class)]
#[CoversClass(Observation::class)]
#[CoversClass(ObservationFields::class)]
final class ActivityObservationTest extends UnitTestCase
{
    public function test_that_activity_roundtrips_private_typed_evidence_through_the_existing_command(): void
    {
        $usage = [
            UsageMeasurement::create('provider/tokens', 'token', 'provider-response', UsageCompleteness::PARTIAL, '00012.500'),
            UsageMeasurement::create('provider/cost', 'USD', 'provider-response', UsageCompleteness::UNAVAILABLE, null),
            UsageMeasurement::create('provider/cache', 'token', 'provider-response', UsageCompleteness::COMPLETE, '0'),
            UsageMeasurement::create('provider/capacity', 'token', 'provider-response', UsageCompleteness::UNKNOWN, null),
        ];
        $activity = PhaseActivity::create('consumer/model-call', 'Safe public summary', 'model:one', $usage);
        $base = ObservationFixture::phase();
        $observation = Observation::forActivity($base->id(), $base->producerId(), $base->session(), $base->phase(), ObservationOccurrence::fromString('2026-10-20T12:34:56.120Z'), $activity);
        $command = new RecordPhaseObservation($observation);
        foreach ([$observation, RecordPhaseObservation::fromArray($command->toArray())->observation(), $observation->recorded(new DateTimeImmutable('2026-10-21T00:00:00.000001Z'))] as $copy) {
            self::assertSame([], get_object_vars($copy));
            self::assertSame(1, $copy->toArray()['version']);
            self::assertSame($observation->comparison(), $copy->comparison());
            self::assertTrue($observation->matches($copy));
            self::assertSame('2026-10-20T12:34:56.120Z', $copy->occurrence()->toString());
            self::assertSame('120000', $copy->occurredAt()->format('u'));
            self::assertSame(ObservationOutcome::UNKNOWN, $copy->outcome());
            self::assertNull($copy->startedAt());
            self::assertNull($copy->endedAt());
            self::assertSame('agent', $copy->phase()->agentId());
            self::assertSame('instrumentation', $copy->producerId());
            self::assertSame('model:one', $copy->activity()->modelId());
            self::assertSame('consumer/model-call', $copy->activity()->kind());
            self::assertSame('Safe public summary', $copy->activity()->summary());
            $measurements = $copy->activity()->usage();
            self::assertSame(['provider/cache', 'provider/capacity', 'provider/cost', 'provider/tokens'], array_map(static fn ($m) => $m->metric(), $measurements));
            self::assertSame('0', $measurements[0]->value());
            self::assertNull($measurements[1]->value());
            self::assertSame(UsageCompleteness::UNKNOWN, $measurements[1]->completeness());
            self::assertSame(UsageCompleteness::UNAVAILABLE, $measurements[2]->completeness());
            self::assertSame('12.5', $measurements[3]->value());
            self::assertSame('token', $measurements[3]->unit());
            self::assertSame('provider-response', $measurements[3]->provenance());
            self::assertSame([], get_object_vars($measurements[3]));
            self::assertSame([], get_object_vars($copy->activity()));
        }
        $event = ObservationRecorded::fromRecorded(new RecordedObservation($observation, new DateTimeImmutable('now')));
        self::assertSame(['observation_id', 'producer_id', 'project_id', 'session_id', 'phase_id', 'recorded_at'], array_keys($event->toArray()));
        self::assertStringNotContainsString('Safe public summary', json_encode($event->toArray()));
        self::assertNull(ObservationFixture::phase()->activity());
        self::assertNull(ObservationFixture::phase()->occurrence());
    }

    public function test_that_all_observation_kinds_use_one_current_version_one_shape(): void
    {
        foreach ([ObservationFixture::session(), ObservationFixture::phase(), ObservationFixture::activity()] as $observation) {
            $data = $observation->toArray();
            self::assertSame(1, $data['version']);
            self::assertSame([
                'version', 'observation_id', 'producer_id', 'session', 'phase', 'occurred_at',
                'started_at', 'ended_at', 'outcome', 'activity', 'detail', 'extensions',
            ], array_keys($data));
            self::assertSame($observation->comparison(), Observation::fromArray($data)->comparison());
            unset($data['activity']);
            try { Observation::fromArray($data); self::fail('Expected missing current field rejection.'); }
            catch (DomainException $exception) { self::assertSame('Invalid observation structure.', $exception->getMessage()); }
        }
    }

    public function test_that_current_activity_is_accepted_as_version_one(): void
    {
        $data = ObservationFixture::phase()->toArray();
        $data['version'] = 1;
        $data['occurred_at'] = '2026-10-20T12:34:56.120Z';
        $data['activity'] = PhaseActivity::create('consumer/model-call', 'Safe summary')->toArray();
        $command = RecordPhaseObservation::fromArray($data);
        self::assertSame(1, $command->toArray()['version']);
        self::assertSame('consumer/model-call', $command->observation()->activity()->kind());
        self::assertSame('2026-10-20T12:34:56.120Z', $command->observation()->occurrence()->toString());
        self::assertEquals($data, $command->toArray());
    }

    public function test_that_version_two_is_unsupported_even_for_activity(): void
    {
        $data = ObservationFixture::activity()->toArray();
        $data['version'] = 2;
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unsupported observation version.');
        RecordPhaseObservation::fromArray($data);
    }

    public function test_that_boundaries_snapshots_and_missing_measurements_remain_honest(): void
    {
        $measurement = UsageMeasurement::create('v/a', str_repeat('u', 128), str_repeat('p', 128), UsageCompleteness::COMPLETE, str_repeat('9', 128));
        $referenced = $measurement;
        $values = [&$referenced];
        $activity = PhaseActivity::create('v/'.str_repeat('a', 94), str_repeat('測', 170).'ab', null, $values);
        $referenced = null;
        self::assertSame([$measurement], $activity->usage());
        $output = $activity->usage();
        $output[0] = null;
        self::assertSame([$measurement], $activity->usage());
        self::assertNull($activity->modelId());
        self::assertSame([], PhaseActivity::create('v/a', '')->usage());
        $many = array_map(static fn (int $n) => UsageMeasurement::create('v/m'.$n, 'count', 'source', UsageCompleteness::COMPLETE, '0'), range(1, 32));
        self::assertCount(32, PhaseActivity::fromArray(PhaseActivity::create('v/a', '', usage: $many)->toArray())->usage());
        $left = PhaseActivity::create('v/a', '', usage: $many);
        $right = PhaseActivity::create('v/a', '', usage: array_reverse($many));
        self::assertSame($left->toArray(), $right->toArray());
    }

    #[DataProvider('occurrences')]
    public function test_that_occurrence_preserves_each_source_precision(string $text, string $micros): void
    {
        $value = ObservationOccurrence::fromString($text);
        self::assertSame($text, $value->toString());
        self::assertSame($micros, $value->instant()->format('u'));
    }

    public static function occurrences(): iterable
    {
        foreach (['' => '000000', '.0' => '000000', '.12' => '120000', '.120' => '120000', '.1200' => '120000', '.12000' => '120000', '.120001' => '120001'] as $fraction => $micros) {
            yield ['2026-10-20T12:34:56'.$fraction.'Z', $micros];
        }
    }

    #[DataProvider('invalidOccurrences')]
    public function test_that_invalid_occurrence_rejects_without_echoing_content(string $text): void
    {
        $this->expectException(DomainException::class);
        ObservationOccurrence::fromString($text);
    }

    public static function invalidOccurrences(): iterable
    {
        foreach (['private body', '2026-02-30T12:00:00Z', '0000-01-01T00:00:00Z', '2026-01-01T00:00:00.1234567Z', '2026-01-01T00:00:00.Z', '2026-01-01T00:00:00+00:00'] as $text) { yield [$text]; }
    }

    #[DataProvider('invalidUsage')]
    public function test_that_invalid_usage_has_fixed_rejections(array $changes): void
    {
        $data = UsageMeasurement::create('v/a', 'token', 'provider', UsageCompleteness::COMPLETE, '0')->toArray();
        $this->expectException(DomainException::class);
        UsageMeasurement::fromArray(array_replace($data, $changes));
    }

    public static function invalidUsage(): iterable
    {
        foreach ([
            ['version' => 2], ['version' => '1'], ['body' => 'private'], ['metric' => 1], ['unit' => null], ['provenance' => []],
            ['metric' => 'tokens'], ['metric' => str_repeat('a', 95).'/a'], ['unit' => ''], ['provenance' => 'bad source'],
            ['completeness' => false], ['completeness' => 'estimated'], ['value' => 0], ['value' => 0.1],
            ['value' => null], ['completeness' => 'partial', 'value' => null], ['completeness' => 'unknown'], ['completeness' => 'unavailable'],
            ['value' => '-1'], ['value' => '1e3'], ['value' => 'NaN'], ['value' => '.1'], ['value' => '1.'], ['value' => ' 0'], ['value' => str_repeat('9', 129)],
        ] as $data) { yield [$data]; }
    }

    #[DataProvider('invalidActivities')]
    public function test_that_invalid_activity_rejects_before_recording(array $changes): void
    {
        $data = PhaseActivity::create('v/a', 'safe')->toArray();
        $this->expectException(DomainException::class);
        PhaseActivity::fromArray(array_replace($data, $changes));
    }

    public static function invalidActivities(): iterable
    {
        $measurement = ['version' => 1, 'metric' => 'v/a', 'unit' => 'token', 'provenance' => 'provider', 'completeness' => 'complete', 'value' => '0'];
        foreach ([
            ['version' => 2], ['body' => 'private'], ['kind' => null], ['summary' => 0], ['model_id' => []], ['model_id' => 'bad model'],
            ['summary' => str_repeat('x', 513)], ['summary' => "\xff"], ['summary' => "safe\nbody"], ['kind' => 'plain'],
            ['usage' => null], ['usage' => ['key' => $measurement]], ['usage' => array_fill(0, 33, $measurement)], ['usage' => [null]],
            ['usage' => [$measurement, $measurement]],
        ] as $data) { yield [$data]; }
    }

    public function test_that_direct_usage_lists_reject_wrong_types_duplicates_and_nonlists(): void
    {
        $measurement = UsageMeasurement::create('v/a', 'token', 'source', UsageCompleteness::COMPLETE, '1');
        foreach ([[null], [$measurement, $measurement], ['key' => $measurement], array_fill(0, 33, $measurement)] as $usage) {
            try { PhaseActivity::create('v/a', '', usage: $usage); self::fail('Expected invalid usage.'); }
            catch (DomainException $exception) { self::assertStringNotContainsString('source', $exception->getMessage()); }
        }
        try { ObservationFields::name(null); self::fail('Expected invalid name.'); }
        catch (DomainException $exception) { self::assertSame('Invalid observation name.', $exception->getMessage()); }
    }

    #[DataProvider('invalidActivityObservations')]
    public function test_that_invalid_activity_core_rejects(array $changes): void
    {
        $base = ObservationFixture::phase();
        $observation = Observation::forActivity($base->id(), 'instrumentation', $base->session(), $base->phase(), ObservationOccurrence::fromString('2026-10-20T12:00:00Z'), PhaseActivity::create('v/a', ''));
        $this->expectException(DomainException::class);
        Observation::fromArray(array_replace($observation->toArray(), $changes));
    }

    public static function invalidActivityObservations(): iterable
    {
        foreach ([['version' => 2], ['body' => 'private'], ['observation_id' => 0], ['observation_id' => 'invalid'], ['session' => null], ['phase' => null], ['activity' => null], ['activity' => false], ['extensions' => null], ['occurred_at' => 0], ['started_at' => '2026-10-20T12:00:00.000000Z'], ['ended_at' => '2026-10-20T12:00:00.000000Z'], ['outcome' => 'succeeded']] as $changes) { yield [$changes]; }
    }

    public function test_that_recording_comparison_uses_time_then_every_uuid_digit_not_occurrence(): void
    {
        $a = ObservationFixture::session(['observation_id' => '019a1234-5678-4abc-8def-000000000001'])->recorded(new DateTimeImmutable('2026-10-21T00:00:00.000001Z'));
        $b = ObservationFixture::session(['observation_id' => '019a1234-5678-4abc-8def-000000000002', 'occurred_at' => '2020-01-01T00:00:00.000000Z'])->recorded($a->recordedAt());
        $c = ObservationFixture::session(['observation_id' => '019a1234-5678-4abc-8def-000000000000'])->recorded(new DateTimeImmutable('2026-10-21T00:00:00.000002Z'));
        self::assertSame(-1, $a->compareRecordingTo($b));
        self::assertSame(1, $b->compareRecordingTo($a));
        self::assertSame(0, $a->compareRecordingTo($a));
        self::assertSame(-1, $b->compareRecordingTo($c));
        self::assertSame(1, $c->compareRecordingTo($b));
    }
}
