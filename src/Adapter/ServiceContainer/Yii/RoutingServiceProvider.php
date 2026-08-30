<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Yii;

use Fight\Common\Adapter\Routing\Yii\YiiUrlGenerator;
use Fight\Common\Application\Routing\UrlGenerator;
use Yiisoft\Definitions\Reference;
use Yiisoft\Di\ServiceProviderInterface;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Class RoutingServiceProvider
 *
 * Registers the Yii routing capability.
 */
final class RoutingServiceProvider implements ServiceProviderInterface
{
    /**
     * Returns routing definitions without boot side effects
     *
     * @return array<string, mixed>
     */
    public function getDefinitions(): array
    {
        return [
            UrlGenerator::class => [
                'class'         => YiiUrlGenerator::class,
                '__construct()' => [Reference::to(UrlGeneratorInterface::class)]
            ]
        ];
    }

    /**
     * Returns no routing extensions
     *
     * @return array<string, callable>
     */
    public function getExtensions(): array
    {
        return [];
    }
}
