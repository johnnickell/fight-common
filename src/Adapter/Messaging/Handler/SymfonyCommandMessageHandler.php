<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Handler;

use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Throwable;

/**
 * Class SymfonyCommandMessageHandler
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
     * @throws Throwable When an error occurs
     */
    public function __invoke(CommandMessage $commandMessage): void
    {
        $this->commandBus->dispatch($commandMessage);
    }
}
