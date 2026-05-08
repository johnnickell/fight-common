<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Handler;

use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Throwable;

/**
 * Class SymfonyEventMessageHandler
 */
final readonly class SymfonyEventMessageHandler
{
    /**
     * Constructs SymfonyEventMessageHandler
     */
    public function __construct(private SynchronousEventDispatcher $eventDispatcher)
    {
    }

    /**
     * @throws Throwable When an error occurs
     */
    public function __invoke(EventMessage $eventMessage): void
    {
        $this->eventDispatcher->dispatch($eventMessage);
    }
}
