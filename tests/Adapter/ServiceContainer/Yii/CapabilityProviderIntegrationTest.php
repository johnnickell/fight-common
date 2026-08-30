<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Yii;

use Fight\Common\Adapter\Filesystem\Symfony\SymfonyFilesystem;
use Fight\Common\Adapter\HttpClient\Psr18\Psr18Client;
use Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory;
use Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport;
use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Adapter\Persistence\Yii\YiiTransactionalUnitOfWork;
use Fight\Common\Adapter\Routing\Yii\YiiUrlGenerator;
use Fight\Common\Adapter\ServiceContainer\Yii\FilesystemServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Yii\HttpClientServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Yii\MailServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Yii\MessagingServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Yii\PersistenceServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Yii\RoutingServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Yii\ViewServiceProvider;
use Fight\Common\Adapter\ServiceContainer\Yii\YiiCapabilityConfiguration;
use Fight\Common\Application\HttpClient\Message\Promise;
use Fight\Common\Application\Filesystem\Filesystem;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Application\Messaging\Command\AsynchronousCommandBus;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Event\AsynchronousEventDispatcher;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Fight\Common\Application\Mail\Message\MailFactory;
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Routing\UrlGenerator;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Common\Application\Templating\TemplateHelper;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Mailer\MailerInterface;
use Yiisoft\Config\Config;
use Yiisoft\Config\ConfigPaths;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Router\FastRoute\UrlGenerator as NativeUrlGenerator;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollector;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\View\View;
use Yiisoft\View\ViewInterface;

#[CoversClass(PersistenceServiceProvider::class)]
#[CoversClass(FilesystemServiceProvider::class)]
#[CoversClass(RoutingServiceProvider::class)]
#[CoversClass(MessagingServiceProvider::class)]
#[CoversClass(HttpClientServiceProvider::class)]
#[CoversClass(MailServiceProvider::class)]
#[CoversClass(ViewServiceProvider::class)]
#[CoversClass(YiiCapabilityConfiguration::class)]
/**
 * Class CapabilityProviderIntegrationTest
 */
final class CapabilityProviderIntegrationTest extends UnitTestCase
{
    /**
     * Tests that persistence selects only its standard collaborators and transactional unit of work
     */
    public function test_that_persistence_group_boots_only_transactional_unit_of_work_and_standard_collaborators(): void
    {
        /** @var CacheInterface&MockInterface $cache */
        $cache = $this->mock(CacheInterface::class);
        /** @var LoggerInterface&MockInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $cache->shouldReceive('set')->once()->with('schema', 'value', 3600)->andReturnTrue();
        $logger->shouldReceive('log')->atLeast()->once();
        $connection = new Connection(new Driver('sqlite::memory:'), new SchemaCache($cache));
        $container = $this->container(
            YiiCapabilityConfiguration::persistence($connection, $cache, $logger),
            'fight-yii-persistence'
        );

        self::assertSame($cache, $container->get(CacheInterface::class));
        self::assertSame($logger, $container->get(LoggerInterface::class));
        $container->get(SchemaCache::class)->set('schema', 'value');
        self::assertSame($connection, $container->get(ConnectionInterface::class));
        self::assertInstanceOf(YiiTransactionalUnitOfWork::class, $container->get(TransactionalUnitOfWork::class));
        self::assertSame(
            $container->get(TransactionalUnitOfWork::class),
            $container->get(TransactionalUnitOfWork::class)
        );
        self::assertSame('committed', $container->get(TransactionalUnitOfWork::class)->commitTransactional(
            static fn (): string => 'committed'
        ));
        self::assertFalse($container->has(UrlGenerator::class));
        self::assertFalse($container->has(CommandMessageHandler::class));
        self::assertFalse($container->has(ClientInterface::class));
    }

    /**
     * Tests that routing selects only the Yii URL generator
     */
    public function test_that_routing_group_boots_only_the_yii_url_generator(): void
    {
        $container = $this->container(
            YiiCapabilityConfiguration::routing($this->native_url_generator()),
            'fight-yii-routing'
        );

        self::assertInstanceOf(YiiUrlGenerator::class, $container->get(UrlGenerator::class));
        self::assertSame('/accounts/42', $container->get(UrlGenerator::class)->generate('account.show', ['id' => 42]));
        self::assertFalse($container->has(TransactionalUnitOfWork::class));
        self::assertFalse($container->has(CommandMessageHandler::class));
        self::assertFalse($container->has(ClientInterface::class));
    }

    /**
     * Tests that HTTP exposes the existing Fight transport through the PSR-18 view
     */
    public function test_that_http_group_exposes_existing_fight_transport_through_psr18_without_a_yii_wrapper(): void
    {
        $transport = new class implements HttpClient {
            /**
             * Sends one synchronous request
             */
            public function send(RequestInterface $request, array $options = []): ResponseInterface
            {
                return new Response();
            }

            /**
             * Rejects asynchronous requests outside the selected PSR-18 lane
             */
            public function sendAsync(RequestInterface $request, array $options = []): Promise
            {
                throw new \LogicException('The Yii HTTP group exposes synchronous PSR-18 only.');
            }
        };
        $container = $this->container(YiiCapabilityConfiguration::http($transport), 'fight-yii-http');

        self::assertSame($transport, $container->get(HttpClient::class));
        self::assertInstanceOf(Psr18Client::class, $container->get(ClientInterface::class));
        self::assertInstanceOf(
            Response::class,
            $container->get(ClientInterface::class)->sendRequest(new Request('GET', 'https://fight.example'))
        );
        self::assertFalse($container->has(TransactionalUnitOfWork::class));
        self::assertFalse($container->has(UrlGenerator::class));
        self::assertFalse($container->has(CommandMessageHandler::class));
    }

    /**
     * Tests that mail selects only the proven Symfony Mail fallback
     */
    public function test_that_mail_group_boots_only_the_proven_symfony_mail_fallback(): void
    {
        /** @var MailerInterface&MockInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once();
        $container = $this->container(YiiCapabilityConfiguration::mail($mailer), 'fight-yii-mail');

        self::assertSame($mailer, $container->get(MailerInterface::class));
        self::assertInstanceOf(SymfonyMailFactory::class, $container->get(MailFactory::class));
        self::assertInstanceOf(SymfonyMailTransport::class, $container->get(MailTransport::class));
        $container->get(MailTransport::class)->send(
            MailMessage::create()
                ->addFrom('from@example.com')
                ->addTo('to@example.com')
                ->addContent('Fallback body', MailMessage::CONTENT_TYPE_PLAIN)
        );
        self::assertFalse($container->has(TransactionalUnitOfWork::class));
        self::assertFalse($container->has(UrlGenerator::class));
        self::assertFalse($container->has(CommandMessageHandler::class));
        self::assertFalse($container->has(ClientInterface::class));
    }

    /**
     * Tests that view selects only the native Yii template engine and caller-owned policy
     */
    public function test_that_view_group_boots_only_the_native_yii_template_engine(): void
    {
        $templatesDirectory = sys_get_temp_dir().'/yii-view-provider-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($templatesDirectory));
        self::assertIsInt(file_put_contents(
            $templatesDirectory.'/page.php',
            '<?= $fight_helper->getName() ?> <?= $name ?>'
        ));
        $view = new View();

        try {
            $container = $this->container(
                YiiCapabilityConfiguration::view($view, $templatesDirectory),
                'fight-yii-view'
            );
            $engine = $container->get(TemplateEngine::class);
            $helper = new class implements TemplateHelper {
                public function getName(): string
                {
                    return 'fight_helper';
                }
            };
            $engine->addHelper($helper);

            self::assertSame($view, $container->get(ViewInterface::class));
            self::assertSame('fight_helper Ada', $engine->render('page.php', ['name' => 'Ada']));
            self::assertFalse($container->has(TransactionalUnitOfWork::class));
            self::assertFalse($container->has(UrlGenerator::class));
            self::assertFalse($container->has(CommandMessageHandler::class));
            self::assertFalse($container->has(ClientInterface::class));
            self::assertFalse($container->has(MailTransport::class));
        } finally {
            $this->removeTemporaryDirectory($templatesDirectory, 'yii-view-provider-');
        }
    }

    /**
     * Tests that filesystem selects only the complete Symfony fallback without path policy
     */
    public function test_that_filesystem_group_boots_only_the_complete_symfony_fallback(): void
    {
        $container = $this->container(YiiCapabilityConfiguration::filesystem(), 'fight-yii-filesystem');
        $temporaryDirectory = sys_get_temp_dir().'/yii-filesystem-provider-'.bin2hex(random_bytes(8));
        $path = $temporaryDirectory.'/nested/file.txt';

        try {
            $filesystem = $container->get(Filesystem::class);

            self::assertInstanceOf(SymfonyFilesystem::class, $filesystem);
            $filesystem->put($path, 'fallback');
            self::assertSame('fallback', $filesystem->get($path));
            self::assertFalse($container->has(TransactionalUnitOfWork::class));
            self::assertFalse($container->has(UrlGenerator::class));
            self::assertFalse($container->has(CommandMessageHandler::class));
            self::assertFalse($container->has(ClientInterface::class));
            self::assertFalse($container->has(MailTransport::class));
            self::assertFalse($container->has(TemplateEngine::class));
        } finally {
            if (is_dir($temporaryDirectory)) {
                $this->removeTemporaryDirectory($temporaryDirectory, 'yii-filesystem-provider-');
            }
        }
    }

    /**
     * Tests that synchronous handlers do not imply stable queue support
     */
    public function test_that_messaging_group_registers_reusable_synchronous_handlers_without_queue_contracts(): void
    {
        $container = $this->container(
            YiiCapabilityConfiguration::messaging($this->mock_command_bus(), $this->mock_event_dispatcher()),
            'fight-yii-messaging'
        );
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 4).'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        self::assertInstanceOf(CommandMessageHandler::class, $container->get(CommandMessageHandler::class));
        self::assertInstanceOf(EventMessageHandler::class, $container->get(EventMessageHandler::class));
        self::assertFalse($container->has(AsynchronousCommandBus::class));
        self::assertFalse($container->has(AsynchronousEventDispatcher::class));
        self::assertArrayNotHasKey('yiisoft/queue', $composer['require']);
        self::assertArrayNotHasKey('yiisoft/queue', $composer['require-dev']);
        self::assertArrayNotHasKey('yiisoft/queue', $composer['suggest']);
        self::assertFalse($container->has(TransactionalUnitOfWork::class));
        self::assertFalse($container->has(UrlGenerator::class));
        self::assertFalse($container->has(ClientInterface::class));
    }

    /**
     * @param array<string, mixed> $definitions
     */
    private function container(array $definitions, string $group): Container
    {
        $configuration = new Config(
            new ConfigPaths(dirname(__DIR__, 4).'/configuration/yii'),
            paramsGroup: null,
            mergePlanFile: 'merge-plan.php'
        );
        $groupConfiguration = $configuration->get($group);

        return new Container(
            ContainerConfig::create()
                ->withStrictMode()
                ->withDefinitions($definitions)
                ->withProviders($groupConfiguration['providers'])
        );
    }

    /**
     * Creates one native Yii URL generator collaborator
     */
    private function native_url_generator(): UrlGeneratorInterface
    {
        $collector = new RouteCollector();
        $collector->addRoute(Route::get('/accounts/{id}')->name('account.show'));

        return new NativeUrlGenerator(new RouteCollection($collector));
    }

    /**
     * Creates a synchronous command-bus collaborator
     */
    private function mock_command_bus(): SynchronousCommandBus&MockInterface
    {
        /** @var SynchronousCommandBus&MockInterface $commandBus */
        $commandBus = $this->mock(SynchronousCommandBus::class);

        return $commandBus;
    }

    /**
     * Creates a synchronous event-dispatcher collaborator
     */
    private function mock_event_dispatcher(): SynchronousEventDispatcher&MockInterface
    {
        /** @var SynchronousEventDispatcher&MockInterface $eventDispatcher */
        $eventDispatcher = $this->mock(SynchronousEventDispatcher::class);

        return $eventDispatcher;
    }
}
