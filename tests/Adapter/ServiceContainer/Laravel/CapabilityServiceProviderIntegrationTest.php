<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Adapter\Persistence\Laravel\LaravelTransactionalUnitOfWork;
use Fight\Common\Adapter\ServiceContainer\Laravel\MessagingServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Laravel\PersistenceServiceProvider;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MessagingServiceProvider::class)]
#[CoversClass(PersistenceServiceProvider::class)]
final class CapabilityServiceProviderIntegrationTest extends UnitTestCase
{
    public function test_that_laravel_remains_a_composer_optional_production_dependency(): void
    {
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 4).'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertArrayNotHasKey('laravel/framework', $composer['require']);
        self::assertSame('^13.0', $composer['require-dev']['laravel/framework'] ?? null);
        self::assertSame(
            'Required by the Laravel queue, transaction, and capability provider adapters',
            $composer['suggest']['laravel/framework'] ?? null,
        );
    }

    public function test_that_messaging_provider_boot_does_not_activate_the_optional_database_capability(): void
    {
        $application = new Application(__DIR__);
        $application->instance(
            SynchronousCommandBus::class,
            $this->mock(SynchronousCommandBus::class)
        );
        $application->instance(
            SynchronousEventDispatcher::class,
            $this->mock(SynchronousEventDispatcher::class)
        );
        $application->register(MessagingServiceProvider::class);
        $application->boot();

        self::assertFalse($application->bound(TransactionalUnitOfWork::class));
        self::assertFalse($application->bound('db'));
        self::assertFalse($application->bound('db.connection'));
        self::assertFalse($application->resolved('db'));
        self::assertFalse($application->resolved('db.connection'));
    }

    public function test_that_real_laravel_applications_boot_selected_capabilities_without_unrelated_activation(): void
    {
        $messagingApplication = new Application(__DIR__);
        $messagingApplication->instance(
            SynchronousCommandBus::class,
            $this->mock(SynchronousCommandBus::class)
        );
        $messagingApplication->instance(
            SynchronousEventDispatcher::class,
            $this->mock(SynchronousEventDispatcher::class)
        );
        $messagingApplication->register(MessagingServiceProvider::class);
        $messagingApplication->boot();

        self::assertTrue($messagingApplication->bound(CommandMessageHandler::class));
        self::assertTrue($messagingApplication->bound(EventMessageHandler::class));
        self::assertFalse($messagingApplication->bound(TransactionalUnitOfWork::class));
        self::assertInstanceOf(CommandMessageHandler::class, $messagingApplication->make(CommandMessageHandler::class));
        self::assertInstanceOf(EventMessageHandler::class, $messagingApplication->make(EventMessageHandler::class));

        $persistenceApplication = new Application(__DIR__);
        $persistenceApplication->instance('db.connection', new Connection(new \PDO('sqlite::memory:')));
        $persistenceApplication->register(PersistenceServiceProvider::class);
        $persistenceApplication->boot();

        self::assertTrue($persistenceApplication->bound(TransactionalUnitOfWork::class));
        self::assertFalse($persistenceApplication->bound(CommandMessageHandler::class));
        self::assertFalse($persistenceApplication->bound(EventMessageHandler::class));
        self::assertInstanceOf(
            LaravelTransactionalUnitOfWork::class,
            $persistenceApplication->make(TransactionalUnitOfWork::class)
        );
    }
}
