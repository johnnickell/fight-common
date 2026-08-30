<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\HttpClient\Guzzle\GuzzleClient;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use GuzzleHttp\ClientInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

/**
 * Class HttpClientServiceProvider
 *
 * Registers the complete Guzzle HTTP transport fallback.
 *
 * Laravel's HTTP client does not accept PSR requests or expose Fight's Promise
 * contract, so it is intentionally not wrapped as a partial transport.
 */
final class HttpClientServiceProvider extends ServiceProvider
{
    /**
     * Registers the HTTP-client capability
     */
    public function register(): void
    {
        $this->app->singleton(HttpClient::class, static function (Container $container): GuzzleClient {
            $client = $container->make(ClientInterface::class);

            return new GuzzleClient($client);
        });
    }
}
