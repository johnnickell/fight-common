<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Command\Sync\Routing;

use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Exception\LookupException;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Type\Type;
use Fight\Common\Domain\Utility\Validate;
use Psr\Container\ContainerInterface;

/**
 * Class ServiceAwareCommandRouter
 */
final class ServiceAwareCommandRouter implements CommandRouter
{
    /**
     * @var array<string, string>
     */
    private array $handlerServiceIdsByType = [];

    /**
     * Constructs ServiceAwareCommandRouter
     */
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * @inheritDoc
     */
    public function match(Command $command): CommandHandler
    {
        return $this->getHandler($command::class);
    }

    /**
     * Registers command handlers
     *
     * The command to handler map must follow this format:
     * [
     *     SomeCommand::class => 'handler_service_name'
     * ]
     *
     * @param array<string, string> $commandToHandlerMap
     */
    public function registerHandlers(array $commandToHandlerMap): void
    {
        foreach ($commandToHandlerMap as $commandClass => $handlerServiceId) {
            $this->registerHandler($commandClass, $handlerServiceId);
        }
    }

    /**
     * Registers a command handler
     */
    public function registerHandler(string $commandClass, string $serviceName): void
    {
        assert(Validate::implementsInterface($commandClass, Command::class));

        $type = Type::create($commandClass)->toString();

        $this->handlerServiceIdsByType[$type] = $serviceName;
    }

    /**
     * @inheritDoc
     */
    public function getHandler(string $commandClass): CommandHandler
    {
        if (!$this->hasHandler($commandClass)) {
            $message = sprintf('Handler not defined for command: %s', $commandClass);
            throw new LookupException($message);
        }

        $type = Type::create($commandClass)->toString();
        $handlerServiceId = $this->handlerServiceIdsByType[$type];

        return $this->container->get($handlerServiceId);
    }

    /**
     * @inheritDoc
     */
    public function hasHandler(string $commandClass): bool
    {
        $type = Type::create($commandClass)->toString();

        if (!isset($this->handlerServiceIdsByType[$type])) {
            return false;
        }

        $handlerServiceId = $this->handlerServiceIdsByType[$type];

        return $this->container->has($handlerServiceId);
    }
}
