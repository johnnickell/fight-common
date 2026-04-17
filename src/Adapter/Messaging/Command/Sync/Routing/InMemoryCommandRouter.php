<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Command\Sync\Routing;

use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Exception\LookupException;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Type\Type;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class InMemoryCommandRouter
 */
final class InMemoryCommandRouter implements CommandRouter
{
    private array $handlers = [];

    /**
     * @inheritDoc
     */
    public function match(Command $command): CommandHandler
    {
        return $this->getHandler(get_class($command));
    }

    /**
     * Registers command handlers
     *
     * The command to handler map must follow this format:
     * [
     *     SomeCommand::class => $someHandlerInstance
     * ]
     */
    public function registerHandlers(array $commandToHandlerMap): void
    {
        foreach ($commandToHandlerMap as $commandClass => $handler) {
            $this->registerHandler($commandClass, $handler);
        }
    }

    /**
     * Registers a command handler
     */
    public function registerHandler(string $commandClass, CommandHandler $handler): void
    {
        assert(Validate::implementsInterface($commandClass, Command::class));

        $type = Type::create($commandClass)->toString();

        $this->handlers[$type] = $handler;
    }

    /**
     * @inheritDoc
     */
    public function getHandler(string $commandClass): CommandHandler
    {
        $type = Type::create($commandClass)->toString();

        if (!isset($this->handlers[$type])) {
            $message = sprintf('Handler not defined for command: %s', $commandClass);
            throw new LookupException($message);
        }

        return $this->handlers[$type];
    }

    /**
     * @inheritDoc
     */
    public function hasHandler(string $commandClass): bool
    {
        $type = Type::create($commandClass)->toString();

        return isset($this->handlers[$type]);
    }
}
