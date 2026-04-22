<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\DependencyInjection;

use Exception;
use Fight\Common\Adapter\Messaging\Query\QueryPipeline;
use Fight\Common\Application\Messaging\Query\QueryFilter;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class QueryFilterCompilerPass
 */
final class QueryFilterCompilerPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(QueryPipeline::class)) {
            return;
        }

        $definition = $container->findDefinition(QueryPipeline::class);
        $taggedServices = $container->findTaggedServiceIds('common.query_filter', true);

        foreach ($taggedServices as $id => $tags) {
            $serviceDefinition = $container->getDefinition($id);

            $serviceClass = $container->getParameterBag()->resolveValue($serviceDefinition->getClass());
            $reflection = new ReflectionClass($serviceClass);

            if (!$reflection->implementsInterface(QueryFilter::class)) {
                $message = sprintf('Service "%s" must implement interface "%s"', $id, QueryFilter::class);
                throw new Exception($message);
            }

            $definition->addMethodCall('addFilter', [new Reference($id)]);
        }
    }
}
