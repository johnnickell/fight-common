<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Routing\Slim;

use Fight\Common\Adapter\Routing\Slim\SlimUrlGenerator;
use Fight\Common\Application\Routing\Exception\InvalidParameterException;
use Fight\Common\Application\Routing\Exception\RouteNotFoundException;
use Fight\Common\Application\Routing\Exception\UrlGenerationException;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Slim\Factory\AppFactory;
use Slim\Interfaces\RouteCollectorInterface;
use Slim\Interfaces\RouteParserInterface;
use RuntimeException;
use Throwable;

#[CoversClass(SlimUrlGenerator::class)]
class SlimUrlGeneratorTest extends UnitTestCase
{
    public function test_that_generate_uses_the_native_route_collector_for_named_route_parameters_and_relative_urls(): void
    {
        $app = AppFactory::create();
        $app->get('/accounts/{id}', static fn() => null)->setName('account.show');
        $generator = new SlimUrlGenerator($app->getRouteCollector(), new Uri('https://fight.example'));

        self::assertSame('/accounts/42?view=summary', $generator->generate('account.show', ['id' => 42], ['view' => 'summary']));
    }

    public function test_that_generate_uses_the_native_route_collector_for_absolute_urls(): void
    {
        $app = AppFactory::create();
        $app->get('/accounts/{id}', static fn() => null)->setName('account.show');
        $generator = new SlimUrlGenerator($app->getRouteCollector(), new Uri('https://fight.example'));

        self::assertSame('https://fight.example/accounts/42', $generator->generate('account.show', ['id' => 42], absolute: true));
    }

    public function test_that_generate_stringifies_scalar_and_array_parameters_for_the_native_parser(): void
    {
        /** @var MockInterface|RouteCollectorInterface $routeCollector */
        $routeCollector = $this->mock(RouteCollectorInterface::class);
        /** @var MockInterface|RouteParserInterface $routeParser */
        $routeParser = $this->mock(RouteParserInterface::class);
        $routeCollector->shouldReceive('getRouteParser')->once()->andReturn($routeParser);
        $routeParser->shouldReceive('urlFor')
            ->once()
            ->with('account.search', ['id' => '42'], ['filter' => ['active' => '1', 'page' => '2']])
            ->andReturn('/accounts/42');

        $generator = new SlimUrlGenerator($routeCollector, new Uri('https://fight.example'));

        self::assertSame(
            '/accounts/42',
            $generator->generate('account.search', ['id' => 42], ['filter' => ['active' => true, 'page' => 2]])
        );
    }

    public function test_that_generate_translates_native_route_lookup_failures(): void
    {
        $generator = $this->generatorThatThrows(new RuntimeException('Route not found'));

        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage('Route not found');

        $generator->generate('missing');
    }

    public function test_that_generate_translates_native_parameter_failures(): void
    {
        $generator = $this->generatorThatThrows(new InvalidArgumentException('Invalid route parameter'));

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Invalid route parameter');

        $generator->generate('account.show');
    }

    public function test_that_generate_translates_unexpected_native_failures(): void
    {
        $generator = $this->generatorThatThrows(new \Error('Unexpected parser failure'));

        $this->expectException(UrlGenerationException::class);
        $this->expectExceptionMessage('Unexpected parser failure');

        $generator->generate('account.show');
    }

    private function generatorThatThrows(Throwable $exception): SlimUrlGenerator
    {
        /** @var MockInterface|RouteCollectorInterface $routeCollector */
        $routeCollector = $this->mock(RouteCollectorInterface::class);
        /** @var MockInterface|RouteParserInterface $routeParser */
        $routeParser = $this->mock(RouteParserInterface::class);
        $routeCollector->shouldReceive('getRouteParser')->once()->andReturn($routeParser);
        $routeParser->shouldReceive('urlFor')->once()->andThrow($exception);

        return new SlimUrlGenerator($routeCollector, new Uri('https://fight.example'));
    }
}
