<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\CodeIgniter;

use Fight\Common\Adapter\Templating\TwigEngine;
use Fight\Common\Application\Templating\TemplateEngine;
use Twig\Environment;

/**
 * Class TemplateServices
 */
final class TemplateServices
{
    /**
     * Creates the Twig template engine fallback
     */
    public static function templateEngine(Environment $environment): TemplateEngine
    {
        return new TwigEngine($environment);
    }
}
