<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Routing\CodeIgniter;

use CodeIgniter\Router\RouteCollectionInterface;
use Fight\Common\Adapter\Routing\CodeIgniter\CodeIgniterUrlGenerator;
use Fight\Common\Application\Routing\Exception\InvalidParameterException;
use Fight\Common\Application\Routing\Exception\MissingParametersException;
use Fight\Common\Application\Routing\Exception\RouteNotFoundException;
use Fight\Common\Application\Routing\Exception\UrlGenerationException;
use Fight\Common\Application\Routing\UrlGenerator;
use Fight\Test\Common\TestCase\Routing\UrlGeneratorConformanceTestCase;
use InvalidArgumentException;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(CodeIgniterUrlGenerator::class)]
final class CodeIgniterUrlGeneratorTest extends UrlGeneratorConformanceTestCase
{
    protected function urlGenerator(): UrlGenerator
    {
        /** @var RouteCollectionInterface&MockInterface $routes */
        $routes = $this->mock(RouteCollectionInterface::class);
        $routes->shouldReceive('reverseRoute')->andReturnUsing(static function (string $name, mixed ...$parameters): string|false {
            if ($name === 'missing') {
                return false;
            }

            if ($parameters === []) {
                throw new InvalidArgumentException('Missing argument for "(:num)" in route "accounts/(:num)".');
            }

            if ($parameters[0] === 'not-an-integer') {
                throw new InvalidArgumentException('Invalid parameter type.');
            }

            return '/accounts/'.$parameters[0];
        });

        return new CodeIgniterUrlGenerator($routes, 'https://fight.example/');
    }

    protected function expectedRelativeUrl(): string
    {
        return '/accounts/42?view=summary';
    }

    protected function expectedAbsoluteUrl(): string
    {
        return 'https://fight.example/accounts/42';
    }

    public function test_that_generate_translates_native_missing_parameter_failures(): void
    {
        $this->expectException(MissingParametersException::class);
        $this->urlGenerator()->generate('account.show');
    }

    public function test_that_generate_translates_native_invalid_parameter_failures(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->urlGenerator()->generate('account.show', ['id' => 'not-an-integer']);
    }

    public function test_that_generate_translates_unexpected_native_failures(): void
    {
        /** @var RouteCollectionInterface&MockInterface $routes */
        $routes = $this->mock(RouteCollectionInterface::class);
        $routes->shouldReceive('reverseRoute')->once()->andThrow(new RuntimeException('native routing failure'));

        $this->expectException(UrlGenerationException::class);
        $this->expectExceptionMessage('native routing failure');
        (new CodeIgniterUrlGenerator($routes, 'https://fight.example'))->generate('account.show');
    }

    public function test_that_generate_translates_a_native_missing_route(): void
    {
        $this->expectException(RouteNotFoundException::class);
        $this->urlGenerator()->generate('missing');
    }
}
