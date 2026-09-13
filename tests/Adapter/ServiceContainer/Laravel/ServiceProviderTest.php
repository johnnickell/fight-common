<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Cache\Laravel\LaravelCache;
use Fight\Common\Adapter\FileStorage\FlysystemStorage;
use Fight\Common\Adapter\Filesystem\Laravel\LaravelFilesystem;
use Fight\Common\Adapter\Http\Laravel\JSendResponse;
use Fight\Common\Adapter\HttpClient\Guzzle\GuzzleClient;
use Fight\Common\Adapter\Mail\Laravel\LaravelMailFactory;
use Fight\Common\Adapter\Mail\Laravel\LaravelMailTransport;
use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Adapter\Messaging\Laravel\LaravelCommandBus;
use Fight\Common\Adapter\Messaging\Laravel\LaravelEventDispatcher;
use Fight\Common\Adapter\Observability\Metrics\NullMetricsCollector;
use Fight\Common\Adapter\Persistence\Laravel\LaravelTransactionalUnitOfWork;
use Fight\Common\Adapter\Process\Symfony\SymfonyProcessRunner;
use Fight\Common\Adapter\Routing\Laravel\LaravelUrlGenerator;
use Fight\Common\Adapter\ServiceContainer\Laravel\BroadcastingServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Laravel\CacheServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Laravel\FileStorageServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Laravel\FilesystemServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Laravel\HttpClientServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Laravel\HttpServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Laravel\LoggingServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Laravel\MailServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Laravel\MessagingServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Laravel\MetricsServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Laravel\PersistenceServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Laravel\ProcessServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Laravel\RoutingServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Laravel\SecurityServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Laravel\TemplatingServiceProvider;
use Fight\Common\Adapter\Socket\Laravel\LaravelBroadcastPublisher;
use Fight\Common\Adapter\Socket\Laravel\LaravelPrivatePublisher;
use Fight\Common\Adapter\Templating\Laravel\LaravelBladeTemplateEngine;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use Fight\Common\Application\Cache\Cache;
use Fight\Common\Application\Cache\MutableCache;
use Fight\Common\Application\FileStorage\FileStorage;
use Fight\Common\Application\Filesystem\Filesystem;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Application\Mail\Message\MailFactory;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Common\Application\Messaging\Command\AsynchronousCommandBus;
use Fight\Common\Application\Messaging\Event\AsynchronousEventDispatcher;
use Fight\Common\Application\Observability\MetricsCollector;
use Fight\Common\Application\Process\ProcessRunner;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Routing\UrlGenerator;
use Fight\Common\Application\Socket\PrivatePublisher;
use Fight\Common\Application\Socket\Publisher;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\ClientInterface;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Routing\UrlGenerator as NativeUrlGenerator;
use Illuminate\Database\Connection;
use Illuminate\Filesystem\Filesystem as IlluminateFilesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use InvalidArgumentException;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;

#[CoversClass(BroadcastingServiceProvider::class)]
#[CoversClass(CacheServiceProvider::class)]
#[CoversClass(FileStorageServiceProvider::class)]
#[CoversClass(FilesystemServiceProvider::class)]
#[CoversClass(HttpClientServiceProvider::class)]
#[CoversClass(HttpServiceProvider::class)]
#[CoversClass(LoggingServiceProvider::class)]
#[CoversClass(MailServiceProvider::class)]
#[CoversClass(MessagingServiceProvider::class)]
#[CoversClass(MetricsServiceProvider::class)]
#[CoversClass(PersistenceServiceProvider::class)]
#[CoversClass(ProcessServiceProvider::class)]
#[CoversClass(RoutingServiceProvider::class)]
#[CoversClass(SecurityServiceProvider::class)]
#[CoversClass(TemplatingServiceProvider::class)]
final class ServiceProviderTest extends UnitTestCase
{
    public function test_that_broadcasting_provider_registers_and_resolves_its_publishers(): void
    {
        $bindings = [];
        $application = $this->application($bindings);
        (new BroadcastingServiceProvider($application))->register();

        $container = new Container();
        $broadcaster = $this->mock(Broadcaster::class);
        $factory = $this->mock(BroadcastingFactory::class);
        $factory->shouldReceive('connection')->once()->andReturn($broadcaster);
        $container->instance(BroadcastingFactory::class, $factory);
        $container->instance('config', new ConfigRepository(['fight' => ['broadcast' => ['event_name' => 'fight.event']]]));

        $publisher = $bindings[Publisher::class]($container);
        $container->instance(Publisher::class, $publisher);
        self::assertInstanceOf(LaravelBroadcastPublisher::class, $publisher);
        self::assertInstanceOf(LaravelPrivatePublisher::class, $bindings[PrivatePublisher::class]($container));
    }

    public function test_that_cache_provider_registers_the_cache_port_and_alias(): void
    {
        $bindings = [];
        $application = $this->application($bindings);
        (new CacheServiceProvider($application))->register();
        $container = new Container();
        $container->instance('cache', new CacheRepository(new ArrayStore()));

        self::assertInstanceOf(LaravelCache::class, $bindings[MutableCache::class]($container));
        self::assertSame(MutableCache::class, $bindings['aliases'][Cache::class]);
    }

    public function test_that_file_storage_provider_resolves_a_selected_disk_and_rejects_an_empty_name(): void
    {
        $bindings = [];
        $application = $this->application($bindings);
        (new FileStorageServiceProvider($application))->register();

        $container = new Container();
        $config = $this->mock(Config::class);
        $config->shouldReceive('get')->once()->with('fight-common.file-storage.disk')->andReturn('uploads');
        $disk = $this->mock(FilesystemAdapter::class);
        $driver = $this->mock(FilesystemOperator::class);
        $disk->shouldReceive('getDriver')->once()->andReturn($driver);
        $factory = $this->mock(FilesystemFactory::class);
        $factory->shouldReceive('disk')->once()->with('uploads')->andReturn($disk);
        $container->instance('config', $config);
        $container->instance(FilesystemFactory::class, $factory);
        self::assertInstanceOf(FlysystemStorage::class, $bindings[FileStorage::class]($container));

        $missingContainer = new Container();
        $missingConfig = $this->mock(Config::class);
        $missingConfig->shouldReceive('get')->once()->andReturn('');
        $missingContainer->instance('config', $missingConfig);
        $this->expectException(InvalidArgumentException::class);
        $bindings[FileStorage::class]($missingContainer);
    }

    public function test_that_filesystem_provider_uses_the_registered_native_filesystem_or_its_fallback(): void
    {
        $bindings = [];
        $application = $this->application($bindings);
        $application->shouldReceive('bound')->with('files')->once()->andReturnFalse();
        (new FilesystemServiceProvider($application))->register();
        self::assertInstanceOf(LaravelFilesystem::class, $bindings[Filesystem::class]($application));

        $native = $this->mock(IlluminateFilesystem::class);
        $registered = $this->mock(Application::class);
        $registered->shouldReceive('bound')->with('files')->once()->andReturnTrue();
        $registered->shouldReceive('make')->with('files')->once()->andReturn($native);
        self::assertInstanceOf(LaravelFilesystem::class, $bindings[Filesystem::class]($registered));
    }

    public function test_that_http_and_http_client_providers_register_their_direct_adapters(): void
    {
        $httpBindings = [];
        (new HttpServiceProvider($this->application($httpBindings)))->register();
        self::assertSame(JSendResponse::class, $httpBindings[JSendResponse::class]);

        $clientBindings = [];
        (new HttpClientServiceProvider($this->application($clientBindings)))->register();
        $container = new Container();
        $container->instance(ClientInterface::class, $this->mock(ClientInterface::class));
        self::assertInstanceOf(GuzzleClient::class, $clientBindings[HttpClient::class]($container));
    }

    public function test_that_logging_metrics_and_process_providers_register_their_simple_bindings(): void
    {
        $logging = [];
        (new LoggingServiceProvider($this->application($logging)))->register();
        self::assertSame('log', $logging['aliases'][LoggerInterface::class]);

        $metrics = [];
        (new MetricsServiceProvider($this->application($metrics)))->register();
        self::assertSame(NullMetricsCollector::class, $metrics[MetricsCollector::class]);

        $process = [];
        (new ProcessServiceProvider($this->application($process)))->register();
        self::assertInstanceOf(SymfonyProcessRunner::class, $process[ProcessRunner::class]());
    }

    public function test_that_mail_messaging_and_persistence_providers_register_their_ports(): void
    {
        $mail = [];
        (new MailServiceProvider($this->application($mail)))->register();
        self::assertSame(LaravelMailFactory::class, $mail[MailFactory::class]);
        $mailContainer = new Container();
        $mailContainer->instance('mailer', $this->mock(Mailer::class));
        self::assertInstanceOf(LaravelMailTransport::class, $mail[MailTransport::class]($mailContainer));

        $messaging = [];
        (new MessagingServiceProvider($this->application($messaging)))->register();
        self::assertSame(CommandMessageHandler::class, $messaging[CommandMessageHandler::class]);
        self::assertSame(EventMessageHandler::class, $messaging[EventMessageHandler::class]);
        self::assertSame(LaravelCommandBus::class, $messaging[AsynchronousCommandBus::class]);
        self::assertSame(LaravelEventDispatcher::class, $messaging[AsynchronousEventDispatcher::class]);

        $persistence = [];
        (new PersistenceServiceProvider($this->application($persistence)))->register();
        $persistenceContainer = new Container();
        $persistenceContainer->instance('db.connection', $this->mock(Connection::class));
        self::assertInstanceOf(
            LaravelTransactionalUnitOfWork::class,
            $persistence[TransactionalUnitOfWork::class]($persistenceContainer)
        );
    }

    public function test_that_routing_security_and_templating_providers_construct_their_adapters(): void
    {
        $routing = [];
        (new RoutingServiceProvider($this->application($routing)))->register();
        $routingContainer = new Container();
        $router = $this->mock(Router::class);
        $router->shouldReceive('getRoutes')->once()->andReturn(new RouteCollection());
        $routingContainer->instance('url', $this->mock(NativeUrlGenerator::class));
        $routingContainer->instance('router', $router);
        self::assertInstanceOf(LaravelUrlGenerator::class, $routing[UrlGenerator::class]($routingContainer));

        $security = [];
        (new SecurityServiceProvider($this->application($security)))->register();
        $securityContainer = new Container();
        $securityContainer->instance(Hasher::class, $this->mock(Hasher::class));
        self::assertInstanceOf(PasswordHasher::class, $security[PasswordHasher::class]($securityContainer));
        self::assertInstanceOf(PasswordValidator::class, $security[PasswordValidator::class]($securityContainer));

        $templating = [];
        (new TemplatingServiceProvider($this->application($templating)))->register();
        $templatingContainer = new Container();
        $templatingContainer->instance('view', $this->mock(\Illuminate\Contracts\View\Factory::class));
        $templatingContainer->instance('fight.templates_path', '/templates');
        self::assertInstanceOf(LaravelBladeTemplateEngine::class, $templating[TemplateEngine::class]($templatingContainer));
    }

    /**
     * @param array<string, mixed> $bindings
     */
    private function application(array &$bindings): Application
    {
        $application = $this->mock(Application::class);
        $application->shouldReceive('singleton')->zeroOrMoreTimes()->andReturnUsing(
            static function (mixed ...$arguments) use (&$bindings): void {
                $bindings[(string) $arguments[0]] = $arguments[1] ?? $arguments[0];
            }
        );
        $application->shouldReceive('alias')->zeroOrMoreTimes()->andReturnUsing(
            static function (string $abstract, string $alias) use (&$bindings): void {
                $bindings['aliases'][$alias] = $abstract;
            }
        );

        return $application;
    }
}
