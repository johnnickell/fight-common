<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Yii;

use Fight\Common\Adapter\HttpClient\Psr18\Psr18Client;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Psr\Http\Client\ClientInterface;
use Yiisoft\Definitions\Reference;
use Yiisoft\Di\ServiceProviderInterface;

/**
 * Class HttpClientServiceProvider
 *
 * Exposes the configured Fight HTTP transport through its PSR-18 view.
 */
final class HttpClientServiceProvider implements ServiceProviderInterface
{
    /**
     * Returns the PSR-18 view definition without boot side effects
     *
     * @return array<string, mixed>
     */
    public function getDefinitions(): array
    {
        return [
            ClientInterface::class => [
                'class'         => Psr18Client::class,
                '__construct()' => [Reference::to(HttpClient::class)]
            ]
        ];
    }

    /**
     * Returns no HTTP extensions
     *
     * @return array<string, callable>
     */
    public function getExtensions(): array
    {
        return [];
    }
}
