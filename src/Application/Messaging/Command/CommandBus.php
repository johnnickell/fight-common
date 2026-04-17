<?php

declare(strict_types=1);

namespace Fight\Common\Application\Messaging\Command;

use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Throwable;

/**
 * Interface CommandBus
 */
interface CommandBus
{
    /**
     * Executes a command
     *
     * The bus should wrap the command in a command message, then dispatch
     *
     * @throws Throwable When an error occurs
     */
    public function execute(Command $command): void;

    /**
     * Dispatches a command message
     *
     * @throws Throwable When an error occurs
     */
    public function dispatch(CommandMessage $commandMessage): void;
}
