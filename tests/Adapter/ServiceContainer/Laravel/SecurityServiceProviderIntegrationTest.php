<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Auth\Security\Laravel\LaravelPasswordHasher;
use Fight\Common\Adapter\Auth\Security\Laravel\LaravelPasswordValidator;
use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Adapter\ServiceContainer\Laravel\SecurityServiceProvider;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Foundation\Application;
use Illuminate\Hashing\BcryptHasher;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SecurityServiceProvider::class)]
final class SecurityServiceProviderIntegrationTest extends UnitTestCase
{
    public function test_that_security_provider_binds_password_ports_without_activating_unrelated_capabilities(): void
    {
        $application = new Application(__DIR__);
        $application->instance(Hasher::class, new BcryptHasher(['rounds' => 4]));
        $application->register(SecurityServiceProvider::class);
        $application->boot();

        self::assertTrue($application->bound(PasswordHasher::class));
        self::assertTrue($application->bound(PasswordValidator::class));
        self::assertFalse($application->bound(CommandMessageHandler::class));
        self::assertFalse($application->bound(EventMessageHandler::class));
        self::assertFalse($application->bound(TransactionalUnitOfWork::class));
        self::assertFalse($application->bound('db'));
        self::assertFalse($application->bound('db.connection'));
        self::assertInstanceOf(LaravelPasswordHasher::class, $application->make(PasswordHasher::class));
        self::assertInstanceOf(LaravelPasswordValidator::class, $application->make(PasswordValidator::class));
    }
}
