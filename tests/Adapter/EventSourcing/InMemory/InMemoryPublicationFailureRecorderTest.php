<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\InMemory;

use Fight\Common\Adapter\EventSourcing\InMemory\InMemoryPublicationFailureRecorder;
use Fight\Common\Application\EventSourcing\EventPublicationFailure;
use Fight\Common\Application\EventSourcing\PublicationFailureRecorder;
use Fight\Test\Common\TestCase\EventSourcing\PublicationFailureRecorderConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryPublicationFailureRecorder::class)]
final class InMemoryPublicationFailureRecorderTest extends PublicationFailureRecorderConformanceTestCase
{
    /**
     * Creates the in-memory publication failure recorder under test
     */
    protected function createPublicationFailureRecorder(): PublicationFailureRecorder
    {
        return new InMemoryPublicationFailureRecorder();
    }

    /**
     * Returns in-memory correlation keys and their first aggregate evidence
     */
    protected function recordedFailureCorrelations(
        PublicationFailureRecorder $recorder,
    ): array {
        self::assertInstanceOf(InMemoryPublicationFailureRecorder::class, $recorder);

        $correlations = array_map(
            static fn (EventPublicationFailure $failure): array => [
                $failure->publicationName(),
                $failure->globalPosition(),
                $failure->streamId()->identifier(),
            ],
            $recorder->failures(),
        );
        sort($correlations);

        return $correlations;
    }
}
