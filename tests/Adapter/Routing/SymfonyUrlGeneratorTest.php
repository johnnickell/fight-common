<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Routing;

use Fight\Common\Adapter\Routing\SymfonyUrlGenerator;
use Fight\Common\Application\Routing\Exception\InvalidParameterException;
use Fight\Common\Application\Routing\Exception\MissingParametersException;
use Fight\Common\Application\Routing\Exception\RouteNotFoundException;
use Fight\Common\Application\Routing\Exception\UrlGenerationException;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Routing\Exception\InvalidParameterException as ParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException as MissingException;
use Symfony\Component\Routing\Exception\RouteNotFoundException as RouteException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(SymfonyUrlGenerator::class)]
class SymfonyUrlGeneratorTest extends UnitTestCase
{
    public function test_that_generate_returns_relative_path(): void
    {
        /** @var MockInterface|UrlGeneratorInterface $symfony */
        $symfony = $this->mock(UrlGeneratorInterface::class);
        $symfony->shouldReceive('generate')
            ->once()
            ->with('route_name', ['id' => 1], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->andReturn('/route/1');

        $generator = new SymfonyUrlGenerator($symfony);

        self::assertSame('/route/1', $generator->generate('route_name', ['id' => 1]));
    }

    public function test_that_generate_returns_absolute_url(): void
    {
        /** @var MockInterface|UrlGeneratorInterface $symfony */
        $symfony = $this->mock(UrlGeneratorInterface::class);
        $symfony->shouldReceive('generate')
            ->once()
            ->with('home', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->andReturn('https://example.com/');

        $generator = new SymfonyUrlGenerator($symfony);

        self::assertSame('https://example.com/', $generator->generate('home', [], [], true));
    }

    public function test_that_generate_appends_query_parameters(): void
    {
        /** @var MockInterface|UrlGeneratorInterface $symfony */
        $symfony = $this->mock(UrlGeneratorInterface::class);
        $symfony->shouldReceive('generate')
            ->once()
            ->with('search', ['q' => 'test'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->andReturn('/search');

        $generator = new SymfonyUrlGenerator($symfony);

        self::assertSame('/search?page=1&sort=asc', $generator->generate('search', ['q' => 'test'], ['page' => 1, 'sort' => 'asc']));
    }

    public function test_that_generate_throws_route_not_found_exception(): void
    {
        /** @var MockInterface|UrlGeneratorInterface $symfony */
        $symfony = $this->mock(UrlGeneratorInterface::class);
        $symfony->shouldReceive('generate')->once()->andThrow(new RouteException('Route not found'));

        $generator = new SymfonyUrlGenerator($symfony);

        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage('Route not found');

        $generator->generate('missing');
    }

    public function test_that_generate_throws_missing_parameters_exception(): void
    {
        /** @var MockInterface|UrlGeneratorInterface $symfony */
        $symfony = $this->mock(UrlGeneratorInterface::class);
        $symfony->shouldReceive('generate')->once()->andThrow(new MissingException('Missing params'));

        $generator = new SymfonyUrlGenerator($symfony);

        $this->expectException(MissingParametersException::class);
        $this->expectExceptionMessage('Missing params');

        $generator->generate('route');
    }

    public function test_that_generate_throws_invalid_parameter_exception(): void
    {
        /** @var MockInterface|UrlGeneratorInterface $symfony */
        $symfony = $this->mock(UrlGeneratorInterface::class);
        $symfony->shouldReceive('generate')->once()->andThrow(new ParameterException('Bad param'));

        $generator = new SymfonyUrlGenerator($symfony);

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Bad param');

        $generator->generate('route');
    }

    public function test_that_generate_throws_url_generation_exception_for_unknown_error(): void
    {
        /** @var MockInterface|UrlGeneratorInterface $symfony */
        $symfony = $this->mock(UrlGeneratorInterface::class);
        $symfony->shouldReceive('generate')->once()->andThrow(new \RuntimeException('Unexpected'));

        $generator = new SymfonyUrlGenerator($symfony);

        $this->expectException(UrlGenerationException::class);
        $this->expectExceptionMessage('Unexpected');

        $generator->generate('route');
    }
}
