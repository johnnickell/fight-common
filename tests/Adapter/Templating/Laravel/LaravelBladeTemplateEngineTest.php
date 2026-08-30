<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Templating\Laravel;

use Fight\Common\Adapter\Templating\Laravel\LaravelBladeTemplateEngine;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Test\Common\TestCase\Templating\TemplateEngineConformanceTestCase;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\FileViewFinder;
use Illuminate\View\Factory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LaravelBladeTemplateEngine::class)]
final class LaravelBladeTemplateEngineTest extends TemplateEngineConformanceTestCase
{
    private string $compiledTemplatesDirectory;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->compiledTemplatesDirectory = sys_get_temp_dir().'/blade-template-conformance-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->compiledTemplatesDirectory));
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory($this->compiledTemplatesDirectory, 'blade-template-conformance-');

        parent::tearDown();
    }

    protected function templateEngine(string $templatesDirectory): TemplateEngine
    {
        $filesystem = new Filesystem();
        $resolver = new EngineResolver();
        $resolver->register(
            'blade',
            fn (): CompilerEngine => new CompilerEngine(new BladeCompiler($filesystem, $this->compiledTemplatesDirectory))
        );

        return new LaravelBladeTemplateEngine(
            new Factory(
                $resolver,
                new FileViewFinder($filesystem, [$templatesDirectory]),
                new Dispatcher()
            ),
            $templatesDirectory
        );
    }

    protected function templateExtension(): string
    {
        return 'blade.php';
    }

    protected function renderingTemplate(): string
    {
        return 'Hello {{ $name }}';
    }

    protected function helperTemplate(): string
    {
        return '{{ $fight_helper->getName() }}';
    }

    protected function failingTemplate(): string
    {
        return '<?php throw new RuntimeException("native rendering failure"); ?>';
    }

    protected function nativeFailureMessage(): string
    {
        return 'native rendering failure';
    }

    public function test_that_exists_rejects_an_unsupported_template_extension(): void
    {
        self::assertFalse(
            $this->templateEngine($this->compiledTemplatesDirectory)->exists('render.unsupported')
        );
    }
}
