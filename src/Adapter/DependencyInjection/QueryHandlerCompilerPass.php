<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\DependencyInjection;

use Exception;
use Fight\Common\Adapter\Messaging\Query\Routing\ServiceAwareQueryRouter;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class QueryHandlerCompilerPass
 */
final class QueryHandlerCompilerPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ServiceAwareQueryRouter::class)) {
            return;
        }

        $definition = $container->findDefinition(ServiceAwareQueryRouter::class);
        $taggedServices = $container->findTaggedServiceIds('common.query_handler', true);

        foreach ($taggedServices as $id => $tags) {
            $serviceDefinition = $container->getDefinition($id);

            if (!$serviceDefinition->isPublic()) {
                $message = sprintf('The service "%s" must be public as query handlers are lazy-loaded', $id);
                throw new Exception($message);
            }

            /** @var QueryHandler|string $serviceClass */
            $serviceClass = $container->getParameterBag()->resolveValue($serviceDefinition->getClass());
            $reflection = new ReflectionClass($serviceClass);

            if (!$reflection->implementsInterface(QueryHandler::class)) {
                $message = sprintf('Service "%s" must implement interface "%s"', $id, QueryHandler::class);
                throw new Exception($message);
            }

            $query = $serviceClass::queryRegistration();

            $definition->addMethodCall('registerHandler', [$query, $id]);
        }
    }
}
