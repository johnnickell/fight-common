<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Symfony;

use Exception;
use Fight\Common\Adapter\Messaging\Query\Routing\ServiceAwareQueryRouter;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class QueryHandlerCompilerPass
 */
class QueryHandlerCompilerPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ServiceAwareQueryRouter::class)) {
            return;
        }

        $queryRouterDefinition = $container->findDefinition(ServiceAwareQueryRouter::class);
        $taggedServices = $container->findTaggedServiceIds('common.query_handler', true);

        foreach ($taggedServices as $handlerServiceId => $tags) {
            $serviceDefinition = $container->getDefinition($handlerServiceId);

            if (!$serviceDefinition->isPublic()) {
                $message = sprintf(
                    'The service "%s" must be public as query handlers are lazy-loaded',
                    $handlerServiceId
                );
                throw new Exception($message);
            }

            /**
             * @var QueryHandler|string $serviceClass
             */
            $serviceClass = $container->getParameterBag()->resolveValue($serviceDefinition->getClass());
            $reflection = new ReflectionClass($serviceClass);

            if (!$reflection->implementsInterface(QueryHandler::class)) {
                $message = sprintf(
                    'Service "%s" must implement interface "%s"',
                    $handlerServiceId,
                    QueryHandler::class
                );
                throw new Exception($message);
            }

            $queryClass = $serviceClass::queryRegistration();

            $queryRouterDefinition->addMethodCall('registerHandler', [$queryClass, $handlerServiceId]);
        }
    }
}
