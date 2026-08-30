<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Templating\Laravel\LaravelBladeTemplateEngine;
use Fight\Common\Application\Templating\TemplateEngine;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\ServiceProvider;

/**
 * Class TemplatingServiceProvider
 *
 * Registers the templating capability.
 */
final class TemplatingServiceProvider extends ServiceProvider
{
    /**
     * Registers the templating capability
     */
    public function register(): void
    {
        $this->app->singleton(
            TemplateEngine::class,
            static function (Container $container): LaravelBladeTemplateEngine {
                $view = $container->make('view');
                assert($view instanceof Factory);
                $templatesPath = $container->make('fight.templates_path');
                assert(is_string($templatesPath));

                return new LaravelBladeTemplateEngine($view, $templatesPath);
            }
        );
    }
}
