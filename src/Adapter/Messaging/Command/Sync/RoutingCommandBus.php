<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Command\Sync;

use Fight\Common\Adapter\Messaging\Command\Sync\Routing\CommandRouter;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;

/**
 * Class RoutingCommandBus
 */
final class RoutingCommandBus implements SynchronousCommandBus
{
    /**
     * Constructs RoutingCommandBus
     */
    public function __construct(private CommandRouter $commandRouter)
    {
    }

    /**
     * @inheritDoc
     */
    public function execute(Command $command): void
    {
        $this->dispatch(CommandMessage::create($command));
    }

    /**
     * @inheritDoc
     */
    public function dispatch(CommandMessage $commandMessage): void
    {
        /** @var Command $command */
        $command = $commandMessage->payload();

        $this->commandRouter->match($command)->handle($commandMessage);
    }
}
