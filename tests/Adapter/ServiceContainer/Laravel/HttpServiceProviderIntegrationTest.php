<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Http\Laravel\JSendResponse;
use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Adapter\ServiceContainer\Laravel\HttpServiceProvider;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use Fight\Common\Application\Cache\Cache;
use Fight\Common\Application\Cache\MutableCache;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HttpServiceProvider::class)]
final class HttpServiceProviderIntegrationTest extends UnitTestCase
{
    public function test_that_http_provider_binds_only_the_jsend_response_capability_in_a_booted_laravel_application(): void
    {
        $application = new Application(__DIR__);
        $application->register(HttpServiceProvider::class);
        $application->boot();

        self::assertTrue($application->bound(JSendResponse::class));
        self::assertFalse($application->bound(PasswordHasher::class));
        self::assertFalse($application->bound(PasswordValidator::class));
        self::assertFalse($application->bound(Cache::class));
        self::assertFalse($application->bound(MutableCache::class));
        self::assertFalse($application->bound(CommandMessageHandler::class));
        self::assertFalse($application->bound(EventMessageHandler::class));
        self::assertFalse($application->bound(TransactionalUnitOfWork::class));
        self::assertFalse($application->bound('db'));
        self::assertFalse($application->bound('db.connection'));
        self::assertTrue($application->bound('router'));
        self::assertFalse($application->resolved('router'));
        self::assertFalse($application->bound('view'));
        self::assertFalse($application->bound('mailer'));
        self::assertFalse($application->bound('queue'));
        self::assertInstanceOf(JSendResponse::class, $application->make(JSendResponse::class));
    }
}
