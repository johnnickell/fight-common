<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Templating\CodeIgniter;

use Fight\Common\Adapter\Templating\TwigEngine;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Test\Common\TestCase\Templating\TemplateEngineConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/** The selected CodeIgniter template fallback retains the complete Fight template engine contract. */
#[CoversClass(TwigEngine::class)]
final class CodeIgniterTemplateFallbackConformanceTest extends TemplateEngineConformanceTestCase
{
    protected function templateEngine(string $templatesDirectory): TemplateEngine
    {
        return new TwigEngine(new Environment(new FilesystemLoader($templatesDirectory)));
    }

    protected function templateExtension(): string
    {
        return 'twig';
    }

    protected function renderingTemplate(): string
    {
        return 'Hello {{ name }}';
    }

    protected function helperTemplate(): string
    {
        return '{{ fight_helper.name }}';
    }

    protected function failingTemplate(): string
    {
        return '{{ 1 / 0 }}';
    }

    protected function nativeFailureMessage(): string
    {
        return 'Division by zero';
    }
}
