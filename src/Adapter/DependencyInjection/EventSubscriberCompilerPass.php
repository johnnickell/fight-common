<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\DependencyInjection;

use Exception;
use Fight\Common\Adapter\Messaging\Event\Sync\ServiceAwareEventDispatcher;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class EventSubscriberCompilerPass
 */
final class EventSubscriberCompilerPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ServiceAwareEventDispatcher::class)) {
            return;
        }

        $definition = $container->findDefinition(ServiceAwareEventDispatcher::class);
        $taggedServices = $container->findTaggedServiceIds('common.event_subscriber', true);

        foreach ($taggedServices as $id => $tags) {
            $serviceDefinition = $container->getDefinition($id);

            if (!$serviceDefinition->isPublic()) {
                $message = sprintf('The service "%s" must be public as event subscribers are lazy-loaded', $id);
                throw new Exception($message);
            }

            /** @var EventSubscriber|string $serviceClass */
            $serviceClass = $container->getParameterBag()->resolveValue($serviceDefinition->getClass());
            $reflection = new ReflectionClass($serviceClass);

            if (!$reflection->implementsInterface(EventSubscriber::class)) {
                $message = sprintf('Service "%s" must implement interface "%s"', $id, EventSubscriber::class);
                throw new Exception($message);
            }

            $definition->addMethodCall('registerService', [$serviceClass, $id]);
        }
    }
}
