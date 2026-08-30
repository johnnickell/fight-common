<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Http\Laravel\JSendResponse;
use Fight\Common\Adapter\Templating\Laravel\LaravelBladeTemplateEngine;
use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Adapter\ServiceContainer\Laravel\TemplatingServiceProvider;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Fight\Common\Application\Auth\Security\PasswordValidator;
use Fight\Common\Application\Cache\Cache;
use Fight\Common\Application\Cache\MutableCache;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Routing\UrlGenerator;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\FileViewFinder;
use Illuminate\View\Factory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TemplatingServiceProvider::class)]
final class TemplatingServiceProviderIntegrationTest extends UnitTestCase
{
    private string $templatesDirectory;
    private string $compiledTemplatesDirectory;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->templatesDirectory = sys_get_temp_dir().'/laravel-provider-templates-'.bin2hex(random_bytes(8));
        $this->compiledTemplatesDirectory = sys_get_temp_dir().'/laravel-provider-compiled-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->templatesDirectory));
        self::assertTrue(mkdir($this->compiledTemplatesDirectory));
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory($this->templatesDirectory, 'laravel-provider-templates-');
        $this->removeTemporaryDirectory($this->compiledTemplatesDirectory, 'laravel-provider-compiled-');

        parent::tearDown();
    }

    public function test_that_templating_provider_binds_only_templating_in_a_booted_real_laravel_application(): void
    {
        $application = new Application(__DIR__);
        $application->instance('view', $this->viewFactory());
        $application->instance('fight.templates_path', $this->templatesDirectory);
        $application->register(TemplatingServiceProvider::class);
        $application->boot();

        self::assertTrue($application->bound(TemplateEngine::class));
        self::assertFalse($application->bound(PasswordHasher::class));
        self::assertFalse($application->bound(PasswordValidator::class));
        self::assertFalse($application->bound(Cache::class));
        self::assertFalse($application->bound(MutableCache::class));
        self::assertFalse($application->bound(JSendResponse::class));
        self::assertFalse($application->bound(UrlGenerator::class));
        self::assertFalse($application->bound(CommandMessageHandler::class));
        self::assertFalse($application->bound(EventMessageHandler::class));
        self::assertFalse($application->bound(TransactionalUnitOfWork::class));
        self::assertFalse($application->bound('db'));
        self::assertFalse($application->bound('db.connection'));
        self::assertTrue($application->bound('router'));
        self::assertFalse($application->resolved('router'));
        self::assertFalse($application->bound('mailer'));
        self::assertFalse($application->bound('queue'));
        self::assertInstanceOf(LaravelBladeTemplateEngine::class, $application->make(TemplateEngine::class));
    }

    private function viewFactory(): Factory
    {
        $filesystem = new Filesystem();
        $resolver = new EngineResolver();
        $resolver->register(
            'blade',
            fn (): CompilerEngine => new CompilerEngine(new BladeCompiler($filesystem, $this->compiledTemplatesDirectory))
        );

        return new Factory(
            $resolver,
            new FileViewFinder($filesystem, [$this->templatesDirectory]),
            new Dispatcher()
        );
    }
}
