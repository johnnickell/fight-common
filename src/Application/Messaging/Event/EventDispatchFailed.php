<?php

declare(strict_types=1);

namespace Fight\Common\Application\Messaging\Event;

use RuntimeException;

/**
 * Class EventDispatchFailed
 *
 * Reports every handler failure from one completed synchronous event dispatch
 */
final class EventDispatchFailed extends RuntimeException
{
    /** @var EventHandlerFailure[] */
    private readonly array $failures;

    /**
     * Constructs EventDispatchFailed
     *
     * @param EventHandlerFailure[] $failures
     */
    public function __construct(array $failures)
    {
        parent::__construct(sprintf('Event dispatch failed in %d handler(s).', count($failures)));
        $this->failures = $failures;
    }

    /**
     * Returns handler failures in invocation order
     *
     * @return EventHandlerFailure[]
     */
    public function failures(): array
    {
        return $this->failures;
    }
}
