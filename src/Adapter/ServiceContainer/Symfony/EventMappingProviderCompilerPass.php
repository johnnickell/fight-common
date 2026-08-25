<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Symfony;

use Fight\Common\Domain\EventSourcing\EventMapper;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class EventMappingProviderCompilerPass
 */
class EventMappingProviderCompilerPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(EventMapper::class)) {
            return;
        }

        $definition = $container->findDefinition(EventMapper::class);
        $taggedServices = $container->findTaggedServiceIds('common.event_mapping_provider', true);

        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall('registerProvider', [new Reference($id)]);
        }
    }
}
