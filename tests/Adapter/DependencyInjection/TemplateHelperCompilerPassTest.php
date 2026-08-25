<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\DependencyInjection;

use Exception;
use Fight\Common\Adapter\DependencyInjection\TemplateHelperCompilerPass;
use Fight\Common\Adapter\ServiceContainer\Symfony\TemplateHelperCompilerPass as CanonicalTemplateHelperCompilerPass;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Common\Application\Templating\TemplateHelper;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(TemplateHelperCompilerPass::class)]
#[CoversClass(CanonicalTemplateHelperCompilerPass::class)]
class TemplateHelperCompilerPassTest extends UnitTestCase
{
    public function test_that_canonical_and_legacy_identities_register_template_helpers(): void
    {
        $deprecations = [];
        set_error_handler(
            static function (int $severity, string $message) use (&$deprecations): bool {
                if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
                    $deprecations[] = $message;
                }

                return false;
            }
        );

        try {
            foreach ([CanonicalTemplateHelperCompilerPass::class, TemplateHelperCompilerPass::class] as $passClass) {
                $container = new ContainerBuilder();
                $engine = $container->register(TemplateEngine::class, TemplateEngine::class);
                $container->register('helper_id', StubTemplateHelper::class)->addTag('common.template_helper');
                $container->addCompilerPass(new $passClass());
                $container->compile();

                $calls = $engine->getMethodCalls();
                self::assertCount(1, $calls);
                self::assertSame('addHelper', $calls[0][0]);
                self::assertSame('helper_id', (string) $calls[0][1][0]);
            }
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
    }

    public function test_that_it_returns_early_when_engine_not_registered(): void
    {
        $container = new ContainerBuilder();
        $pass = new TemplateHelperCompilerPass();
        $pass->process($container);

        self::assertFalse($container->has(TemplateEngine::class));
    }

    public function test_that_it_registers_tagged_template_helpers(): void
    {
        $container = new ContainerBuilder();
        $engineDef = $container->register(TemplateEngine::class, TemplateEngine::class);

        $helperDef = $container->register('helper_id', StubTemplateHelper::class);
        $helperDef->addTag('common.template_helper', []);

        $pass = new TemplateHelperCompilerPass();
        $pass->process($container);

        $calls = $engineDef->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('addHelper', $calls[0][0]);
        self::assertInstanceOf(Reference::class, $calls[0][1][0]);
        self::assertSame('helper_id', (string) $calls[0][1][0]);
    }

    public function test_that_it_registers_multiple_tagged_template_helpers(): void
    {
        $container = new ContainerBuilder();
        $engineDef = $container->register(TemplateEngine::class, TemplateEngine::class);

        $helperDef1 = $container->register('helper_1', StubTemplateHelper::class);
        $helperDef1->addTag('common.template_helper', []);

        $helperDef2 = $container->register('helper_2', StubOtherTemplateHelper::class);
        $helperDef2->addTag('common.template_helper', []);

        $pass = new TemplateHelperCompilerPass();
        $pass->process($container);

        $calls = $engineDef->getMethodCalls();
        self::assertCount(2, $calls);
        self::assertSame('addHelper', $calls[0][0]);
        self::assertSame('helper_1', (string) $calls[0][1][0]);
        self::assertSame('addHelper', $calls[1][0]);
        self::assertSame('helper_2', (string) $calls[1][1][0]);
    }

    public function test_that_it_throws_when_tagged_helper_does_not_implement_template_helper(): void
    {
        $container = new ContainerBuilder();
        $container->register(TemplateEngine::class, TemplateEngine::class);

        $badDef = $container->register('bad_helper', StubTplHelperNonHelperService::class);
        $badDef->addTag('common.template_helper', []);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must implement interface');

        $pass = new TemplateHelperCompilerPass();
        $pass->process($container);
    }
}

class StubTemplateHelper implements TemplateHelper
{
    public function getName(): string
    {
        return 'stub_helper';
    }
}

class StubOtherTemplateHelper implements TemplateHelper
{
    public function getName(): string
    {
        return 'other_helper';
    }
}

class StubTplHelperNonHelperService
{
}
