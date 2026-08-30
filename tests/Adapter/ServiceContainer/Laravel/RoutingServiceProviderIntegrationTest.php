<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Auth\Security\Laravel\LaravelPasswordHasher;
use Fight\Common\AdapterCache\Laravel\LaravelCache;
use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Adapter\Routing\Laravel\LaravelUrlGenerator;
use Fight\Common\Adapter\ServiceContainer\Laravel\RoutingServiceProvider;
use Fight\Common\Application\Routing\UrlGenerator;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator as NativeUrlGenerator;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RoutingServiceProvider::class)]
final class RoutingServiceProviderIntegrationTest extends UnitTestCase
{
    public function test_that_routing_provider_binds_only_routing_in_a_booted_real_laravel_application(): void
    {
        $application = new Application(__DIR__);
        $router = new Router(new Dispatcher($application), $application);
        $router->get('/accounts/{id}', static function (): void {
        })->name('account.show')->whereNumber('id');
        $router->getRoutes()->refreshNameLookups();
        $application->instance('router', $router);
        $application->instance(
            'url',
            new NativeUrlGenerator($router->getRoutes(), Request::create('https://fight.example'))
        );
        $application->register(RoutingServiceProvider::class);
        $application->boot();

        self::assertTrue($application->bound(UrlGenerator::class));
        self::assertFalse($application->bound(LaravelCache::class));
        self::assertFalse($application->bound(LaravelPasswordHasher::class));
        self::assertFalse($application->bound(CommandMessageHandler::class));
        self::assertFalse($application->bound(EventMessageHandler::class));
        self::assertFalse($application->bound('db'));
        self::assertFalse($application->bound('db.connection'));
        self::assertFalse($application->bound('view'));
        self::assertFalse($application->bound('mailer'));
        self::assertFalse($application->bound('queue'));
        self::assertInstanceOf(LaravelUrlGenerator::class, $application->make(UrlGenerator::class));
        self::assertSame(
            '/accounts/42?view=summary',
            $application->make(UrlGenerator::class)->generate('account.show', ['id' => 42], ['view' => 'summary'])
        );
    }
}
