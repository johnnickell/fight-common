<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Yii;

use Fight\Common\Adapter\Routing\Yii\YiiUrlGenerator;
use Fight\Common\Adapter\ServiceContainer\Yii\RoutingServiceProvider;
use Fight\Common\Application\Routing\UrlGenerator;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Yiisoft\Definitions\Reference;
use Yiisoft\Router\UrlGeneratorInterface;

#[CoversClass(RoutingServiceProvider::class)]
final class RoutingServiceProviderTest extends UnitTestCase
{
    public function test_that_get_definitions_binds_the_yii_url_generator(): void
    {
        self::assertEquals(
            [
                UrlGenerator::class => [
                    'class'         => YiiUrlGenerator::class,
                    '__construct()' => [Reference::to(UrlGeneratorInterface::class)]
                ]
            ],
            (new RoutingServiceProvider())->getDefinitions()
        );
    }

    public function test_that_get_extensions_returns_no_extensions(): void
    {
        self::assertSame([], (new RoutingServiceProvider())->getExtensions());
    }
}
