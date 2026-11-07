<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Symfony;

use Exception;
use Fight\Common\Adapter\Messaging\Command\Sync\CommandPipeline;
use Fight\Common\Application\Messaging\Command\CommandFilter;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class CommandFilterCompilerPass
 */
class CommandFilterCompilerPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(CommandPipeline::class)) {
            return;
        }

        $commandPipelineDefinition = $container->findDefinition(CommandPipeline::class);
        $taggedServices = $container->findTaggedServiceIds('common.command_filter', true);

        foreach ($taggedServices as $filterServiceId => $tags) {
            $serviceDefinition = $container->getDefinition($filterServiceId);

            $serviceClass = $container->getParameterBag()->resolveValue($serviceDefinition->getClass());
            $reflection = new ReflectionClass($serviceClass);

            if (!$reflection->implementsInterface(CommandFilter::class)) {
                $message = sprintf(
                    'Service "%s" must implement interface "%s"',
                    $filterServiceId,
                    CommandFilter::class
                );
                throw new Exception($message);
            }

            $commandPipelineDefinition->addMethodCall('addFilter', [new Reference($filterServiceId)]);
        }
    }
}
