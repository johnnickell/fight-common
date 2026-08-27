<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Fight;

use Fight\Common\Adapter\Routing\Slim\SlimUrlGenerator;
use Fight\Common\Adapter\ServiceContainer\Fight\ContainerCapabilityRegistrar;
use Fight\Common\Application\HttpClient\Message\Promise;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\ServiceAwareCommandRouter;
use Fight\Common\Application\Routing\UrlGenerator;
use Fight\Common\Application\Service\Container;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Factory\AppFactory;

#[CoversClass(ContainerCapabilityRegistrar::class)]
#[CoversClass(SlimUrlGenerator::class)]
class SlimCompositionTest extends UnitTestCase
{
    public function test_that_an_explicit_fight_container_slim_composition_resolves_only_selected_capabilities(): void
    {
        $container = new Container();
        $app = AppFactory::create();
        $app->get('/accounts/{id}', static fn() => null)->setName('account.show');
        $transport = new class implements HttpClient {
            public function send(RequestInterface $request, array $options = []): ResponseInterface
            {
                return new Response();
            }

            public function sendAsync(RequestInterface $request, array $options = []): Promise
            {
                throw new \LogicException('The composition only selects synchronous transport.');
            }
        };

        ContainerCapabilityRegistrar::registerHttpClient($container, static fn(): HttpClient => $transport);
        ContainerCapabilityRegistrar::registerMessaging(
            $container,
            [
                ServiceAwareCommandRouter::class => static fn(Container $container): ServiceAwareCommandRouter => new ServiceAwareCommandRouter($container),
                'selected.collaborator' => static fn(): \stdClass => new \stdClass(),
            ],
            [],
            [],
            [],
            [],
            [],
            []
        );
        $container->set(
            UrlGenerator::class,
            static fn(): SlimUrlGenerator => new SlimUrlGenerator($app->getRouteCollector(), new Uri('https://fight.example'))
        );

        self::assertInstanceOf(ClientInterface::class, $container->get(ClientInterface::class));
        self::assertInstanceOf(ServiceAwareCommandRouter::class, $container->get(ServiceAwareCommandRouter::class));
        self::assertInstanceOf(SlimUrlGenerator::class, $container->get(UrlGenerator::class));
        self::assertInstanceOf(\stdClass::class, $container->get('selected.collaborator'));
        self::assertFalse($container->has('unselected.capability'));
        self::assertSame('/accounts/42', $container->get(UrlGenerator::class)->generate('account.show', ['id' => 42]));
        self::assertInstanceOf(
            Response::class,
            $container->get(ClientInterface::class)->sendRequest(new Request('GET', 'https://fight.example'))
        );
    }
}
