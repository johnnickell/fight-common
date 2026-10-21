<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Execution;

use DateTimeImmutable;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Observation\ObservationId;
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

    public static function phase(array $changes = []): Observation
    {
        return self::session(array_replace([
            'observation_id' => '019a1234-5678-4abc-8def-123456789abd',
            'phase' => PhaseReference::fromStrings('session', 'phase', null, 'agent', 'review', 1)->toArray(),
        ], $changes));
    }
}
