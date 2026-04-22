<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\DependencyInjection;

use Exception;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\ServiceAwareCommandRouter;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class CommandHandlerCompilerPass
 */
final class CommandHandlerCompilerPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ServiceAwareCommandRouter::class)) {
            return;
        }

        $definition = $container->findDefinition(ServiceAwareCommandRouter::class);
        $taggedServices = $container->findTaggedServiceIds('common.command_handler', true);

        foreach ($taggedServices as $id => $tags) {
            $serviceDefinition = $container->getDefinition($id);

            if (!$serviceDefinition->isPublic()) {
                $message = sprintf('The service "%s" must be public as command handlers are lazy-loaded', $id);
                throw new Exception($message);
            }

            /** @var CommandHandler|string $serviceClass */
            $serviceClass = $container->getParameterBag()->resolveValue($serviceDefinition->getClass());
            $reflection = new ReflectionClass($serviceClass);

            if (!$reflection->implementsInterface(CommandHandler::class)) {
                $message = sprintf('Service "%s" must implement interface "%s"', $id, CommandHandler::class);
                throw new Exception($message);
            }

            $command = $serviceClass::commandRegistration();

            $definition->addMethodCall('registerHandler', [$command, $id]);
        }
    }
}
