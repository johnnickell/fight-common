<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\DependencyInjection;

use Exception;
use Fight\Common\Adapter\Messaging\Command\CommandPipeline;
use Fight\Common\Application\Messaging\Command\CommandFilter;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class CommandFilterCompilerPass
 */
final class CommandFilterCompilerPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(CommandPipeline::class)) {
            return;
        }

        $definition = $container->findDefinition(CommandPipeline::class);
        $taggedServices = $container->findTaggedServiceIds('common.command_filter', true);

        foreach ($taggedServices as $id => $tags) {
            $serviceDefinition = $container->getDefinition($id);

            $serviceClass = $container->getParameterBag()->resolveValue($serviceDefinition->getClass());
            $reflection = new ReflectionClass($serviceClass);

            if (!$reflection->implementsInterface(CommandFilter::class)) {
                $message = sprintf('Service "%s" must implement interface "%s"', $id, CommandFilter::class);
                throw new Exception($message);
            }

            $definition->addMethodCall('addFilter', [new Reference($id)]);
        }
    }
}
