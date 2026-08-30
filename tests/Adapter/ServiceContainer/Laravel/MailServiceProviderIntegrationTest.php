<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Http\Laravel\JSendResponse;
use Fight\Common\Adapter\Mail\Laravel\LaravelMailFactory;
use Fight\Common\Adapter\Mail\Laravel\LaravelMailTransport;
use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Adapter\ServiceContainer\Laravel\MailServiceProvider;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use Fight\Common\Application\Cache\Cache;
use Fight\Common\Application\Cache\MutableCache;
use Fight\Common\Application\Mail\Message\MailFactory;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Routing\UrlGenerator;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MailServiceProvider::class)]
final class MailServiceProviderIntegrationTest extends UnitTestCase
{
    public function test_that_mail_provider_binds_only_mail_transport_and_factory_in_a_booted_real_laravel_application(): void
    {
        $application = new Application(__DIR__);
        $application->instance('mailer', $this->mock(Mailer::class));
        $application->register(MailServiceProvider::class);
        $application->boot();

        self::assertTrue($application->bound(MailFactory::class));
        self::assertTrue($application->bound(MailTransport::class));
        self::assertFalse($application->bound(PasswordHasher::class));
        self::assertFalse($application->bound(PasswordValidator::class));
        self::assertFalse($application->bound(Cache::class));
        self::assertFalse($application->bound(MutableCache::class));
        self::assertFalse($application->bound(JSendResponse::class));
        self::assertFalse($application->bound(UrlGenerator::class));
        self::assertFalse($application->bound(TemplateEngine::class));
        self::assertFalse($application->bound(CommandMessageHandler::class));
        self::assertFalse($application->bound(EventMessageHandler::class));
        self::assertFalse($application->bound(TransactionalUnitOfWork::class));
        self::assertFalse($application->bound('db'));
        self::assertFalse($application->bound('db.connection'));
        self::assertTrue($application->bound('router'));
        self::assertFalse($application->resolved('router'));
        self::assertFalse($application->bound('view'));
        self::assertFalse($application->bound('queue'));
        self::assertInstanceOf(LaravelMailFactory::class, $application->make(MailFactory::class));
        self::assertInstanceOf(LaravelMailTransport::class, $application->make(MailTransport::class));
    }
}
