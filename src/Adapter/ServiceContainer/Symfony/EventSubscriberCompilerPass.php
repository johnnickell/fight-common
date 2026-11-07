<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Symfony;

use Exception;
use Fight\Common\Adapter\Messaging\Event\Sync\ServiceAwareEventDispatcher;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class EventSubscriberCompilerPass
 */
class EventSubscriberCompilerPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ServiceAwareEventDispatcher::class)) {
            return;
        }

        $eventDispatcherDefinition = $container->findDefinition(ServiceAwareEventDispatcher::class);
        $taggedServices = $container->findTaggedServiceIds('common.event_subscriber', true);

        foreach ($taggedServices as $subscriberServiceId => $tags) {
            $serviceDefinition = $container->getDefinition($subscriberServiceId);

            if (!$serviceDefinition->isPublic()) {
                $message = sprintf(
                    'The service "%s" must be public as event subscribers are lazy-loaded',
                    $subscriberServiceId
                );
                throw new Exception($message);
            }

            /**
             * @var EventSubscriber|string $serviceClass
             */
            $serviceClass = $container->getParameterBag()->resolveValue($serviceDefinition->getClass());
            $reflection = new ReflectionClass($serviceClass);

            if (!$reflection->implementsInterface(EventSubscriber::class)) {
                $message = sprintf(
                    'Service "%s" must implement interface "%s"',
                    $subscriberServiceId,
                    EventSubscriber::class
                );
                throw new Exception($message);
            }

            $eventDispatcherDefinition->addMethodCall('registerService', [$serviceClass, $subscriberServiceId]);
        }
    }
}
