<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Socket\Laravel\LaravelBroadcastPublisher;
use Fight\Common\Adapter\Socket\Laravel\LaravelPrivatePublisher;
use Fight\Common\Adapter\ServiceContainer\Laravel\BroadcastingServiceProvider;
use Fight\Common\Application\Cache\Cache;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Routing\UrlGenerator;
use Fight\Common\Application\Socket\Publisher;
use Fight\Common\Application\Socket\PrivatePublisher;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BroadcastingServiceProvider::class)]
final class BroadcastingServiceProviderIntegrationTest extends UnitTestCase
{
    public function test_that_broadcasting_provider_binds_public_and_private_publishers_with_the_native_broadcaster(): void
    {
        $application = new Application(__DIR__);
        $broadcaster = $this->mock(Broadcaster::class);
        $factory = $this->mock(Factory::class);
        $factory->shouldReceive('connection')
            ->once()
            ->withNoArgs()
            ->andReturn($broadcaster);
        $application->instance(Factory::class, $factory);
        $application->instance('config', new ConfigRepository([
            'fight' => ['broadcast' => ['event_name' => 'fight.socket.message']],
        ]));
        $application->register(BroadcastingServiceProvider::class);
        $application->boot();

        self::assertTrue($application->bound(Publisher::class));
        self::assertTrue($application->bound(PrivatePublisher::class));
        self::assertFalse($application->bound(Cache::class));
        self::assertFalse($application->bound(MailTransport::class));
        self::assertFalse($application->bound(SynchronousCommandBus::class));
        self::assertFalse($application->bound(TransactionalUnitOfWork::class));
        self::assertFalse($application->bound(UrlGenerator::class));
        self::assertFalse($application->bound(TemplateEngine::class));
        self::assertFalse($application->bound('db'));
        self::assertFalse($application->bound('mailer'));
        self::assertFalse($application->bound('queue'));
        self::assertTrue($application->bound('router'));
        self::assertFalse($application->resolved('router'));
        self::assertFalse($application->bound('view'));
        self::assertInstanceOf(LaravelBroadcastPublisher::class, $application->make(Publisher::class));
        self::assertInstanceOf(LaravelPrivatePublisher::class, $application->make(PrivatePublisher::class));
    }
}
