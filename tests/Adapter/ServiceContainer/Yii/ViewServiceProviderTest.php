<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Yii;

use Fight\Common\Adapter\ServiceContainer\Yii\ViewServiceProvider;
use Fight\Common\Adapter\Templating\Yii\YiiTemplateEngine;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Yiisoft\Definitions\Reference;
use Yiisoft\View\ViewInterface;

#[CoversClass(ViewServiceProvider::class)]
final class ViewServiceProviderTest extends UnitTestCase
{
    public function test_that_get_definitions_binds_the_yii_template_engine_to_application_collaborators(): void
    {
        self::assertEquals(
            [
                TemplateEngine::class => [
                    'class'         => YiiTemplateEngine::class,
                    '__construct()' => [
                        Reference::to(ViewInterface::class),
                        Reference::to('fight.templates_path')
                    ]
                ]
            ],
            (new ViewServiceProvider())->getDefinitions()
        );
    }

    public function test_that_get_extensions_returns_no_extensions(): void
    {
        self::assertSame([], (new ViewServiceProvider())->getExtensions());
    }
}
