<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Handler;

use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Throwable;

/**
 * Class SymfonyCommandMessageHandler
 *
 * @deprecated since 1.2.0, use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler
 */
final readonly class SymfonyCommandMessageHandler
{
    /**
     * Constructs SymfonyCommandMessageHandler
     */
    public function __construct(private SynchronousCommandBus $commandBus)
    {
    }

    /**
     * Dispatches one command message synchronously
     *
     * @throws Throwable When an error occurs
     */
    public function __invoke(CommandMessage $commandMessage): void
    {
        $this->commandBus->dispatch($commandMessage);
    }
}
