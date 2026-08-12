<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSourcing\InMemory;

use Fight\Common\Application\EventSourcing\EventPublicationFailure;
use Fight\Common\Application\EventSourcing\PublicationFailureRecorder;

/**
 * Class InMemoryPublicationFailureRecorder
 *
 * In-memory reference adapter for publication-failure recording
 */
final class InMemoryPublicationFailureRecorder implements PublicationFailureRecorder
{
    /** @var array<string, EventPublicationFailure> */
    private array $failures = [];

    /**
     * Records one aggregated publication failure
     */
    public function record(EventPublicationFailure $failure): void
    {
        $key = sprintf('%s:%d', $failure->publicationName(), $failure->globalPosition());
        $this->failures[$key] ??= $failure;
    }

    /**
     * Returns recorded failures for in-memory inspection
     *
     * @return list<EventPublicationFailure>
     */
    public function failures(): array
    {
        return array_values($this->failures);
    }
}
