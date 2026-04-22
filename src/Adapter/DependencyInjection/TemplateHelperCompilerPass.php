<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\DependencyInjection;

use Exception;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Common\Application\Templating\TemplateHelper;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class TemplateHelperCompilerPass
 */
final class TemplateHelperCompilerPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(TemplateEngine::class)) {
            return;
        }

        $definition = $container->findDefinition(TemplateEngine::class);
        $taggedServices = $container->findTaggedServiceIds('common.template_helper');

        foreach ($taggedServices as $id => $tags) {
            $serviceDefinition = $container->getDefinition($id);

            $serviceClass = $container->getParameterBag()->resolveValue($serviceDefinition->getClass());
            $reflection = new ReflectionClass($serviceClass);

            if (!$reflection->implementsInterface(TemplateHelper::class)) {
                $message = sprintf('Service "%s" must implement interface "%s"', $id, TemplateHelper::class);
                throw new Exception($message);
            }

            $definition->addMethodCall('addHelper', [new Reference($id)]);
        }
    }
}
