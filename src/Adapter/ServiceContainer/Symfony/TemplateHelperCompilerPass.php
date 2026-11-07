<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Symfony;

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
class TemplateHelperCompilerPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(TemplateEngine::class)) {
            return;
        }

        $templateEngineDefinition = $container->findDefinition(TemplateEngine::class);
        $taggedServices = $container->findTaggedServiceIds('common.template_helper');

        foreach ($taggedServices as $helperServiceId => $tags) {
            $serviceDefinition = $container->getDefinition($helperServiceId);

            $serviceClass = $container->getParameterBag()->resolveValue($serviceDefinition->getClass());
            $reflection = new ReflectionClass($serviceClass);

            if (!$reflection->implementsInterface(TemplateHelper::class)) {
                $message = sprintf(
                    'Service "%s" must implement interface "%s"',
                    $helperServiceId,
                    TemplateHelper::class
                );
                throw new Exception($message);
            }

            $templateEngineDefinition->addMethodCall('addHelper', [new Reference($helperServiceId)]);
        }
    }
}
