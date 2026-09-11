<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Templating\Yii;

use Fight\Common\Adapter\Templating\Yii\YiiTemplateEngine;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Test\Common\TestCase\Templating\TemplateEngineConformanceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Yiisoft\View\View;

#[CoversClass(YiiTemplateEngine::class)]
final class YiiTemplateEngineTest extends TemplateEngineConformanceTestCase
{
    protected function templateEngine(string $templatesDirectory): TemplateEngine
    {
        return new YiiTemplateEngine(new View(), $templatesDirectory);
    }

    protected function templateExtension(): string
    {
        return 'php';
    }

    protected function renderingTemplate(): string
    {
        return 'Hello <?= $name ?>';
    }

    protected function helperTemplate(): string
    {
        return '<?= $fight_helper->getName() ?>';
    }

    protected function failingTemplate(): string
    {
        return '<?php throw new RuntimeException("native rendering failure"); ?>';
    }

    protected function nativeFailureMessage(): string
    {
        return 'native rendering failure';
    }
}
