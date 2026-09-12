<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\CodeIgniter;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Queue\Interfaces\QueueInterface;
use CodeIgniter\Router\RouteCollectionInterface;
use Fight\Common\Adapter\Cache\CodeIgniter\CodeIgniterCache;
use Fight\Common\Adapter\Messaging\CodeIgniter\QueueCommandBus;
use Fight\Common\Adapter\Messaging\CodeIgniter\QueueEventDispatcher;
use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Adapter\Persistence\CodeIgniter\CodeIgniterTransactionalUnitOfWork;
use Fight\Common\Adapter\Routing\CodeIgniter\CodeIgniterUrlGenerator;
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\CacheServices;
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\FilesystemServices;
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\MailServices;
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\MessagingServices;
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\PersistenceServices;
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\RoutingServices;
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\TemplateServices;
use Fight\Common\Application\Cache\MutableCache;
use Fight\Common\Application\Filesystem\Filesystem;
use Fight\Common\Application\Mail\Message\MailFactory;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Common\Application\Messaging\Command\AsynchronousCommandBus;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Event\AsynchronousEventDispatcher;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Routing\UrlGenerator;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;

#[CoversClass(CacheServices::class)]
#[CoversClass(FilesystemServices::class)]
#[CoversClass(MailServices::class)]
#[CoversClass(MessagingServices::class)]
#[CoversClass(PersistenceServices::class)]
#[CoversClass(RoutingServices::class)]
#[CoversClass(TemplateServices::class)]
final class CapabilityServicesTest extends UnitTestCase
{
    public function test_that_capability_factories_create_their_native_and_neutral_adapters(): void
    {
        $queue = $this->mock(QueueInterface::class);
        self::assertInstanceOf(QueueCommandBus::class, MessagingServices::queueCommandBus($queue, 'commands', 'command'));
        self::assertInstanceOf(
            AsynchronousCommandBus::class,
            MessagingServices::asynchronousCommandBus($queue, 'commands', 'command')
        );
        self::assertInstanceOf(QueueEventDispatcher::class, MessagingServices::queueEventDispatcher($queue, 'events', 'event'));
        self::assertInstanceOf(
            AsynchronousEventDispatcher::class,
            MessagingServices::asynchronousEventDispatcher($queue, 'events', 'event')
        );

        $commandBus = $this->mock(SynchronousCommandBus::class);
        self::assertInstanceOf(CommandMessageHandler::class, MessagingServices::commandMessageHandler($commandBus));
        $eventDispatcher = $this->mock(SynchronousEventDispatcher::class);
        self::assertInstanceOf(EventMessageHandler::class, MessagingServices::eventMessageHandler($eventDispatcher));

        $connection = $this->mock(BaseConnection::class);
        $connection->shouldReceive('getConnection')->twice()->andReturnFalse();
        self::assertInstanceOf(
            CodeIgniterTransactionalUnitOfWork::class,
            PersistenceServices::codeIgniterTransactionalUnitOfWork($connection)
        );
        self::assertInstanceOf(TransactionalUnitOfWork::class, PersistenceServices::transactionalUnitOfWork($connection));

        $cache = $this->mock(CacheInterface::class);
        self::assertInstanceOf(CodeIgniterCache::class, CacheServices::cache($cache));
        self::assertInstanceOf(MutableCache::class, CacheServices::mutableCache($cache));

        $routes = $this->mock(RouteCollectionInterface::class);
        self::assertInstanceOf(CodeIgniterUrlGenerator::class, RoutingServices::urlGenerator($routes, 'https://fight.example'));
        self::assertInstanceOf(UrlGenerator::class, RoutingServices::routing($routes, 'https://fight.example'));

        $mailer = $this->mock(MailerInterface::class);
        self::assertInstanceOf(MailFactory::class, MailServices::mailFactory());
        self::assertInstanceOf(MailTransport::class, MailServices::mailTransport($mailer));
        $twig = $this->mock(Environment::class);
        self::assertInstanceOf(TemplateEngine::class, TemplateServices::templateEngine($twig));
        self::assertInstanceOf(Filesystem::class, FilesystemServices::filesystem());
    }
}
