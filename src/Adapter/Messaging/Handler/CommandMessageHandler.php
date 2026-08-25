<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Handler;

use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Throwable;

/**
 * Class CommandMessageHandler
 */
final readonly class CommandMessageHandler
{
    /**
     * Constructs CommandMessageHandler
     */
    public function __construct(private SynchronousCommandBus $commandBus)
    {
    }

    /**
     * Dispatches one command message synchronously
     *
     * @throws Throwable When synchronous command handling fails
     */
    public function __invoke(CommandMessage $commandMessage): void
    {
        $this->commandBus->dispatch($commandMessage);
    }
}
