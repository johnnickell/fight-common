<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Execution;

use DateTimeImmutable;
use Fight\Common\Domain\Execution\Activity\PhaseActivity;
use Fight\Common\Domain\Execution\Detail\CaptureCompleteness;
use Fight\Common\Domain\Execution\Detail\DetailCapture;
use Fight\Common\Domain\Execution\Detail\DetailKind;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Observation\ObservationId;
use Fight\Common\Domain\Execution\Observation\ObservationOccurrence;
use Fight\Common\Domain\Execution\Usage\UsageCompleteness;
use Fight\Common\Domain\Execution\Usage\UsageMeasurement;
use Fight\Common\Domain\Execution\Phase\PhaseReference;
use Fight\Common\Domain\Execution\Session\SessionReference;

final class ObservationFixture
{
    public static function session(array $changes = []): Observation
    {
        $observation = Observation::create(
            ObservationId::fromString('019a1234-5678-4abc-8def-123456789abc'),
            'instrumentation',
            SessionReference::fromStrings('project', 'session', 'build:147'),
            null,
            new DateTimeImmutable('2026-10-20T12:34:56.123456+00:00'),
        );

        return Observation::fromArray(array_replace($observation->toArray(), $changes));
    }

    public static function activity(array $changes = []): Observation
    {
        $phase = self::phase();
        $observation = Observation::forActivity(
            ObservationId::fromString('019a1234-5678-4abc-8def-123456789abe'),
            'instrumentation', $phase->session(), $phase->phase(),
            ObservationOccurrence::fromString('2026-10-20T12:34:56.120Z'),
            PhaseActivity::create('consumer/model-call', 'Safe summary', 'model:one', [
                UsageMeasurement::create('provider/input', 'token', 'response', UsageCompleteness::COMPLETE, '0'),
                UsageMeasurement::create('provider/output', 'token', 'response', UsageCompleteness::PARTIAL, '12.5'),
                UsageMeasurement::create('provider/cost', 'USD', 'response', UsageCompleteness::UNAVAILABLE, null),
                UsageMeasurement::create('provider/capacity', 'token', 'response', UsageCompleteness::UNKNOWN, null),
            ])
        );
        return Observation::fromArray(array_replace($observation->toArray(), $changes));
    }

    public static function detail(array $changes = []): Observation
    {
        $base = self::phase();
        $observation = Observation::forDetail(
            ObservationId::fromString('019a1234-5678-4abc-8def-123456789abf'),
            'instrumentation', $base->session(), $base->phase(),
            ObservationOccurrence::fromString('2026-10-20T12:34:56.12Z'),
            DetailCapture::create(self::activity()->id(), DetailKind::MESSAGE, 'text/plain', CaptureCompleteness::COMPLETE)
        );
        return Observation::fromArray(array_replace($observation->toArray(), $changes));
    }

    public static function phase(array $changes = []): Observation
    {
        return self::session(array_replace([
            'observation_id' => '019a1234-5678-4abc-8def-123456789abd',
            'phase' => PhaseReference::fromStrings('session', 'phase', null, 'agent', 'review', 1)->toArray(),
        ], $changes));
    }
}
