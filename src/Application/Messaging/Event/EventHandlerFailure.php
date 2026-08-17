<?php

declare(strict_types=1);

namespace Fight\Common\Application\Messaging\Event;

use Throwable;

/**
 * Class EventHandlerFailure
 *
 * Describes one synchronous event handler failure
 */
final readonly class EventHandlerFailure
{
    /**
     * Constructs EventHandlerFailure
     */
    public function __construct(
        private string $callableDescription,
        private Throwable $throwable
    ) {
    }

    /**
     * Returns the diagnostic callable description
     */
    public function callableDescription(): string
    {
        return $this->callableDescription;
    }

    /**
     * Returns the original handler throwable
     */
    public function throwable(): Throwable
    {
        return $this->throwable;
    }
}
