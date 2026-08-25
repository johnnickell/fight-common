<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Handler;

use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Throwable;

/**
 * Class EventMessageHandler
 */
final readonly class EventMessageHandler
{
    /**
     * Constructs EventMessageHandler
     */
    public function __construct(private SynchronousEventDispatcher $eventDispatcher)
    {
    }

    /**
     * Dispatches one event message synchronously
     *
     * @throws Throwable When synchronous event handling fails
     */
    public function __invoke(EventMessage $eventMessage): void
    {
        $this->eventDispatcher->dispatch($eventMessage);
    }
}
