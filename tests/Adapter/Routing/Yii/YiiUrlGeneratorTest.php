<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Routing\Yii;

use Fight\Common\Adapter\Routing\Yii\YiiUrlGenerator;
use Fight\Common\Application\Routing\Exception\InvalidParameterException;
use Fight\Common\Application\Routing\Exception\MissingParametersException;
use Fight\Common\Application\Routing\Exception\UrlGenerationException;
use Fight\Common\Application\Routing\UrlGenerator;
use Fight\Test\Common\TestCase\Routing\UrlGeneratorConformanceTestCase;
use InvalidArgumentException;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Throwable;
use Yiisoft\Router\FastRoute\UrlGenerator as NativeUrlGenerator;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollector;
use Yiisoft\Router\UrlGeneratorInterface;

#[CoversClass(YiiUrlGenerator::class)]
class YiiUrlGeneratorTest extends UrlGeneratorConformanceTestCase
{
    protected function urlGenerator(): UrlGenerator
    {
        $collector = new RouteCollector();
        $collector->addRoute(Route::get('/accounts/{id:\\d+}')->name('account.show'));

        return new YiiUrlGenerator(
            new NativeUrlGenerator(
                new RouteCollection($collector),
                scheme: 'https',
                host: 'fight.example'
            )
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

    public function test_that_generate_translates_native_missing_route_arguments(): void
    {
        $this->expectException(MissingParametersException::class);
        $this->expectExceptionMessage('expects at least argument values');

        $this->urlGenerator()->generate('account.show');
    }

    public function test_that_generate_translates_native_invalid_route_arguments(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('did not match the regex');

        $this->urlGenerator()->generate('account.show', ['id' => 'not-an-integer']);
    }

    public function test_that_generate_translates_unexpected_native_failures(): void
    {
        $generator = $this->generator_that_throws(new RuntimeException('Unexpected native failure'));

        $this->expectException(UrlGenerationException::class);
        $this->expectExceptionMessage('Unexpected native failure');

        $generator->generate('account.show');
    }

    public function test_that_generate_translates_invalid_native_parameter_failures(): void
    {
        $generator = $this->generator_that_throws(new InvalidArgumentException('Invalid native parameter'));

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Invalid native parameter');

        $generator->generate('account.show');
    }

    private function generator_that_throws(Throwable $exception): YiiUrlGenerator
    {
        /** @var MockInterface|UrlGeneratorInterface $native */
        $native = $this->mock(UrlGeneratorInterface::class);
        $native->shouldReceive('generate')->once()->andThrow($exception);

        return new YiiUrlGenerator($native);
    }
}
