<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Yii;

use Fight\Common\Adapter\Templating\Yii\YiiTemplateEngine;
use Fight\Common\Application\Templating\TemplateEngine;
use Yiisoft\Definitions\Reference;
use Yiisoft\Di\ServiceProviderInterface;
use Yiisoft\View\ViewInterface;

/**
 * Class ViewServiceProvider
 */
final class ViewServiceProvider implements ServiceProviderInterface
{
    /**
     * Returns view definitions without boot side effects
     *
     * @return array<string, mixed>
     */
    public function getDefinitions(): array
    {
        return [
            TemplateEngine::class => [
                'class'         => YiiTemplateEngine::class,
                '__construct()' => [
                    Reference::to(ViewInterface::class),
                    Reference::to('fight.templates_path')
                ]
            ]
        ];
    }

    /**
     * Returns no view extensions
     *
     * @return array<string, callable>
     */
    public function getExtensions(): array
    {
        return [];
    }
}
