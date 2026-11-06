<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Symfony;

use Exception;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\ServiceAwareCommandRouter;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class CommandHandlerCompilerPass
 */
class CommandHandlerCompilerPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ServiceAwareCommandRouter::class)) {
            return;
        }

        $commandRouterDefinition = $container->findDefinition(ServiceAwareCommandRouter::class);
        $taggedServices = $container->findTaggedServiceIds('common.command_handler', true);

        foreach ($taggedServices as $handlerServiceId => $tags) {
            $serviceDefinition = $container->getDefinition($handlerServiceId);

            if (!$serviceDefinition->isPublic()) {
                $message = sprintf(
                    'The service "%s" must be public as command handlers are lazy-loaded',
                    $handlerServiceId
                );
                throw new Exception($message);
            }

            /**
             * @var CommandHandler|string $serviceClass
             */
            $serviceClass = $container->getParameterBag()->resolveValue($serviceDefinition->getClass());
            $reflection = new ReflectionClass($serviceClass);

            if (!$reflection->implementsInterface(CommandHandler::class)) {
                $message = sprintf(
                    'Service "%s" must implement interface "%s"',
                    $handlerServiceId,
                    CommandHandler::class
                );
                throw new Exception($message);
            }

            $commandClass = $serviceClass::commandRegistration();

            $commandRouterDefinition->addMethodCall('registerHandler', [$commandClass, $handlerServiceId]);
        }
    }
}
