<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Routing\Laravel;

use Exception;
use Fight\Common\Adapter\Routing\Laravel\LaravelUrlGenerator;
use Fight\Common\Application\Routing\Exception\InvalidParameterException;
use Fight\Common\Application\Routing\Exception\MissingParametersException;
use Fight\Common\Application\Routing\Exception\RouteNotFoundException;
use Fight\Common\Application\Routing\Exception\UrlGenerationException;
use Fight\Common\Application\Routing\UrlGenerator;
use Fight\Test\Common\TestCase\Routing\UrlGeneratorConformanceTestCase;
use Illuminate\Contracts\Routing\UrlGenerator as NativeUrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException as NativeUrlGenerationException;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator as NativeLaravelUrlGenerator;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Routing\Exception\RouteNotFoundException as NativeRouteNotFoundException;

#[CoversClass(LaravelUrlGenerator::class)]
final class LaravelUrlGeneratorTest extends UrlGeneratorConformanceTestCase
{
    protected function urlGenerator(): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add(
            (new Route(['GET'], '/accounts/{id}', static function (): void {
            }))->name('account.show')->whereNumber('id')
        );

        return new LaravelUrlGenerator(
            new NativeLaravelUrlGenerator($routes, Request::create('https://fight.example')),
            $routes
        );
    }

    protected function expectedRelativeUrl(): string
    {
        return '/accounts/42?view=summary';
    }

    protected function expectedAbsoluteUrl(): string
    {
        return 'https://fight.example/accounts/42';
    }

    public function test_that_generate_translates_native_missing_route_failures(): void
    {
        $generator = $this->generatorThatThrows(new NativeRouteNotFoundException('Route [missing] not defined.'));

        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage('Route [missing] not defined.');

        $generator->generate('missing');
    }

    public function test_that_generate_translates_native_missing_required_parameter_failures(): void
    {
        $generator = $this->generatorThatThrows(
            new NativeUrlGenerationException(
                'Missing required parameter for [Route: account.show] [URI: accounts/{id}].'
            )
        );

        $this->expectException(MissingParametersException::class);
        $this->expectExceptionMessage('Missing required parameter');

        $generator->generate('account.show');
    }

    public function test_that_generate_translates_native_invalid_parameter_failures(): void
    {
        $generator = $this->generatorThatThrows(new \InvalidArgumentException('Invalid native parameter.'));

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Invalid native parameter.');

        $generator->generate('account.show');
    }

    public function test_that_generate_translates_unexpected_native_failures(): void
    {
        $generator = $this->generatorThatThrows(new Exception('Unexpected native failure.'));

        $this->expectException(UrlGenerationException::class);
        $this->expectExceptionMessage('Unexpected native failure.');

        $generator->generate('account.show');
    }

    public function test_that_generate_works_without_optional_route_constraint_introspection(): void
    {
        /** @var MockInterface|NativeUrlGenerator $native */
        $native = $this->mock(NativeUrlGenerator::class);
        $native->shouldReceive('route')->once()->with('account.show', ['id' => 42], false)->andReturn('/accounts/42');

        self::assertSame('/accounts/42', (new LaravelUrlGenerator($native))->generate('account.show', ['id' => 42]));
    }

    public function test_that_generate_allows_an_omitted_optional_constrained_parameter(): void
    {
        $routes = new RouteCollection();
        $routes->add(
            (new Route(['GET'], '/accounts/{id?}', static function (): void {
            }))->name('account.optional')->whereNumber('id')
        );
        $generator = new LaravelUrlGenerator(
            new NativeLaravelUrlGenerator($routes, Request::create('https://fight.example')),
            $routes
        );

        self::assertSame('/accounts', $generator->generate('account.optional'));
    }

    private function generatorThatThrows(\Throwable $exception): LaravelUrlGenerator
    {
        /** @var MockInterface|NativeUrlGenerator $native */
        $native = $this->mock(NativeUrlGenerator::class);
        $native->shouldReceive('route')->once()->andThrow($exception);

        return new LaravelUrlGenerator($native);
    }
}
