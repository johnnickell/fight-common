<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Yii;

use Fight\Common\Adapter\HttpClient\Psr18\Psr18Client;
use Fight\Common\Adapter\ServiceContainer\Yii\HttpClientServiceProvider;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Client\ClientInterface;
use Yiisoft\Definitions\Reference;

#[CoversClass(HttpClientServiceProvider::class)]
final class HttpClientServiceProviderTest extends UnitTestCase
{
    public function test_that_get_definitions_exposes_the_psr18_view_of_the_fight_transport(): void
    {
        self::assertEquals(
            [
                ClientInterface::class => [
                    'class'         => Psr18Client::class,
                    '__construct()' => [Reference::to(HttpClient::class)]
                ]
            ],
            (new HttpClientServiceProvider())->getDefinitions()
        );
    }

    public function test_that_get_extensions_returns_no_extensions(): void
    {
        self::assertSame([], (new HttpClientServiceProvider())->getExtensions());
    }
}
