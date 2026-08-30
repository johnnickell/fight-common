<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Templating;

use Mockery;
use Fight\Common\Adapter\Templating\TwigEngine;
use Fight\Common\Application\Templating\Exception\DuplicateHelperException;
use Fight\Common\Application\Templating\Exception\TemplatingException;
use Fight\Common\Application\Templating\TemplateHelper;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Test\Common\TestCase\Templating\TemplateEngineConformanceTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;

#[CoversClass(TwigEngine::class)]
class TwigEngineTest extends TemplateEngineConformanceTestCase
{
    protected function templateEngine(string $templatesDirectory): TemplateEngine
    {
        return new TwigEngine(new Environment(new FilesystemLoader($templatesDirectory)));
    }

    protected function templateExtension(): string
    {
        return 'twig';
    }

    protected function renderingTemplate(): string
    {
        return 'Hello {{ name }}';
    }

    protected function helperTemplate(): string
    {
        return '{{ fight_helper.name }}';
    }

    protected function failingTemplate(): string
    {
        return '{{ 1 / 0 }}';
    }

    protected function nativeFailureMessage(): string
    {
        return 'Division by zero';
    }

    public function test_that_render_returns_rendered_template(): void
    {
        /** @var MockInterface|Environment $environment */
        $environment = $this->mock(Environment::class);
        $environment->shouldReceive('render')->once()->with('template.html.twig', ['key' => 'value'])->andReturn('rendered');

        $engine = new TwigEngine($environment);
        $result = $engine->render('template.html.twig', ['key' => 'value']);

        self::assertSame('rendered', $result);
    }

    public function test_that_render_wraps_exception_in_templating_exception(): void
    {
        /** @var MockInterface|Environment $environment */
        $environment = $this->mock(Environment::class);
        $environment->shouldReceive('render')->once()->andThrow(new RuntimeException('twig error'));

        $engine = new TwigEngine($environment);

        $this->expectException(TemplatingException::class);
        $this->expectExceptionMessage('twig error');

        $engine->render('template.html.twig');
    }

    public function test_that_exists_returns_true_when_template_exists(): void
    {
        /** @var MockInterface|Environment $environment */
        $environment = $this->mock(Environment::class);
        $loader = $this->mock(LoaderInterface::class);
        $loader->shouldReceive('exists')->once()->with('page.html.twig')->andReturn(true);
        $environment->shouldReceive('getLoader')->once()->andReturn($loader);

        $engine = new TwigEngine($environment);

        self::assertTrue($engine->exists('page.html.twig'));
    }

    public function test_that_exists_returns_false_when_template_does_not_exist(): void
    {
        /** @var MockInterface|Environment $environment */
        $environment = $this->mock(Environment::class);
        $loader = $this->mock(LoaderInterface::class);
        $loader->shouldReceive('exists')->once()->with('missing.twig')->andReturn(false);
        $environment->shouldReceive('getLoader')->once()->andReturn($loader);

        $engine = new TwigEngine($environment);

        self::assertFalse($engine->exists('missing.twig'));
    }

    public function test_that_supports_returns_true_for_twig_extension(): void
    {
        /** @var MockInterface|Environment $environment */
        $environment = $this->mock(Environment::class);

        $engine = new TwigEngine($environment);

        self::assertTrue($engine->supports('template.twig'));
    }

    public function test_that_supports_returns_false_for_non_twig_extension(): void
    {
        /** @var MockInterface|Environment $environment */
        $environment = $this->mock(Environment::class);

        $engine = new TwigEngine($environment);

        self::assertFalse($engine->supports('template.php'));
        self::assertFalse($engine->supports('template.html'));
    }

    public function test_that_add_helper_adds_to_helpers_and_adds_global(): void
    {
        /** @var MockInterface|Environment $environment */
        $environment = $this->mock(Environment::class);
        $environment->shouldReceive('addGlobal')->once()->with('my_helper', Mockery::type(TemplateHelper::class));

        $helper = new class implements TemplateHelper {
            public function getName(): string { return 'my_helper'; }
        };

        $engine = new TwigEngine($environment);
        $engine->addHelper($helper);

        self::assertTrue($engine->hasHelper($helper));
    }

    public function test_that_add_helper_throws_on_duplicate(): void
    {
        /** @var MockInterface|Environment $environment */
        $environment = $this->mock(Environment::class);
        $environment->shouldReceive('addGlobal')->once();

        $helper = new class implements TemplateHelper {
            public function getName(): string { return 'dup'; }
        };

        $engine = new TwigEngine($environment);
        $engine->addHelper($helper);

        $this->expectException(DuplicateHelperException::class);

        $engine->addHelper($helper);
    }

    public function test_that_has_helper_returns_false_when_helper_not_registered(): void
    {
        /** @var MockInterface|Environment $environment */
        $environment = $this->mock(Environment::class);

        $helper = new class implements TemplateHelper {
            public function getName(): string { return 'unknown'; }
        };

        $engine = new TwigEngine($environment);

        self::assertFalse($engine->hasHelper($helper));
    }
}
