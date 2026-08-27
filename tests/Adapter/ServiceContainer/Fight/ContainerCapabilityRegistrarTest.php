<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Fight;

use Fight\Common\Adapter\HttpClient\Psr18\Psr18Client;
use Fight\Common\Adapter\Messaging\Command\Sync\CommandPipeline;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\ServiceAwareCommandRouter;
use Fight\Common\Adapter\Messaging\Event\Sync\ServiceAwareEventDispatcher;
use Fight\Common\Adapter\Messaging\Query\QueryPipeline;
use Fight\Common\Adapter\Messaging\Query\Routing\ServiceAwareQueryRouter;
use Fight\Common\Adapter\ServiceContainer\Fight\ContainerCapabilityRegistrar;
use Fight\Common\Application\HttpClient\Message\Promise;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Application\Messaging\Query\QueryFilter;
use Fight\Common\Application\Service\Container;
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

#[CoversClass(ContainerCapabilityRegistrar::class)]
class ContainerCapabilityRegistrarTest extends UnitTestCase
{
    public function test_that_register_messaging_registers_only_the_explicit_services_and_maps(): void
    {
        $container = new Container();
        $commandRouter = new ServiceAwareCommandRouter($container);
        $queryRouter = new ServiceAwareQueryRouter($container);
        $eventDispatcher = new ServiceAwareEventDispatcher($container);
        $commandPipeline = new CommandPipeline($this->mockCommandBus());
        $queryPipeline = new QueryPipeline($this->mockQueryBus());

        ContainerCapabilityRegistrar::registerMessaging(
            $container,
            [
                'command.router' => fn(): ServiceAwareCommandRouter => $commandRouter,
                'query.router' => fn(): ServiceAwareQueryRouter => $queryRouter,
                'event.dispatcher' => fn(): ServiceAwareEventDispatcher => $eventDispatcher,
                'command.pipeline' => fn(): CommandPipeline => $commandPipeline,
                'query.pipeline' => fn(): QueryPipeline => $queryPipeline,
                'command.handler' => fn(): object => new \stdClass(),
                'query.handler' => fn(): object => new \stdClass(),
                'event.subscriber' => fn(): object => new \stdClass(),
                'command.filter' => fn(): CommandFilter => new class implements CommandFilter {
                    public function process(\Fight\Common\Domain\Messaging\Command\CommandMessage $message, callable $next): void
                    {
                        $next($message);
                    }
                },
                'query.filter' => fn(): QueryFilter => new class implements QueryFilter {
                    public function process(\Fight\Common\Domain\Messaging\Query\QueryMessage $message, callable $next): void
                    {
                        $next($message);
                    }
                },
            ],
            [],
            [StubCommand::class => 'command.handler'],
            [StubQuery::class => 'query.handler'],
            [StubSubscriber::class => 'event.subscriber'],
            [
                'command.pipeline' => ['command.filter'],
                'query.pipeline' => ['query.filter'],
            ],
            [
                'command.router' => 'command.router',
                'query.router' => 'query.router',
                'event.dispatcher' => 'event.dispatcher',
            ]
        );

        self::assertTrue($commandRouter->hasHandler(StubCommand::class));
        self::assertTrue($queryRouter->hasHandler(StubQuery::class));
        self::assertTrue($eventDispatcher->hasHandlers());
        self::assertTrue($container->has('command.filter'));
        self::assertFalse($container->has('unselected.capability'));
    }

    public function test_that_register_template_helpers_uses_explicit_helper_and_collaborator_maps(): void
    {
        $container = new Container();
        /** @var TemplateEngine&MockInterface $engine */
        $engine = $this->mock(TemplateEngine::class);
        $helper = new class implements TemplateHelper {
            public function getName(): string
            {
                return 'test';
            }
        };
        $engine->shouldReceive('addHelper')->once()->with($helper);

        ContainerCapabilityRegistrar::registerTemplateHelpers(
            $container,
            ['helper' => fn(): TemplateHelper => $helper],
            ['template.engine' => fn(): TemplateEngine => $engine],
            ['template.engine' => ['helper']]
        );

        self::assertSame($helper, $container->get('helper'));
        self::assertSame($engine, $container->get('template.engine'));
    }

    public function test_that_register_http_client_exposes_one_transport_as_fight_and_psr18_contracts(): void
    {
        $container = new Container();
        $transport = new StubHttpClient();

        ContainerCapabilityRegistrar::registerHttpClient($container, fn(): HttpClient => $transport);

        $fightClient = $container->get(HttpClient::class);
        $psrClient = $container->get(ClientInterface::class);

        self::assertSame($transport, $fightClient);
        self::assertInstanceOf(Psr18Client::class, $psrClient);
        self::assertFalse($this->isFightHttpClient($psrClient));
        self::assertSame($transport->response, $psrClient->sendRequest(new Request('GET', 'https://example.com')));
    }

    private function mockCommandBus(): \Fight\Common\Application\Messaging\Command\SynchronousCommandBus&MockInterface
    {
        /** @var \Fight\Common\Application\Messaging\Command\SynchronousCommandBus&MockInterface $commandBus */
        $commandBus = $this->mock(\Fight\Common\Application\Messaging\Command\SynchronousCommandBus::class);

        return $commandBus;
    }

    private function mockQueryBus(): \Fight\Common\Application\Messaging\Query\QueryBus&MockInterface
    {
        /** @var \Fight\Common\Application\Messaging\Query\QueryBus&MockInterface $queryBus */
        $queryBus = $this->mock(\Fight\Common\Application\Messaging\Query\QueryBus::class);

        return $queryBus;
    }

    private function isFightHttpClient(mixed $client): bool
    {
        return $client instanceof HttpClient;
    }
}

final class StubCommand implements \Fight\Common\Domain\Messaging\Command\Command
{
    public static function fromArray(array $data): static
    {
        return new static();
    }

    public function toArray(): array
    {
        return [];
    }
}

final class StubQuery implements \Fight\Common\Domain\Messaging\Query\Query
{
    public static function fromArray(array $data): static
    {
        return new static();
    }

    public function toArray(): array
    {
        return [];
    }
}

final class StubSubscriber implements \Fight\Common\Application\Messaging\Event\EventSubscriber
{
    public static function eventRegistration(): array
    {
        return [StubEvent::class => 'handle'];
    }
}

final class StubEvent implements \Fight\Common\Domain\Messaging\Event\Event
{
    public static function fromArray(array $data): static
    {
        return new static();
    }

    public function toArray(): array
    {
        return [];
    }
}

final class StubHttpClient implements HttpClient
{
    public ResponseInterface $response;

    public function __construct()
    {
        $this->response = new Response();
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        return $this->response;
    }

    public function sendAsync(RequestInterface $request, array $options = []): Promise
    {
        throw new \LogicException('Not needed by the PSR-18 adapter.');
    }
}
