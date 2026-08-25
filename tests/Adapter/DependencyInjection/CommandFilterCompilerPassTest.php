<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\DependencyInjection;

use Exception;
use Fight\Common\Adapter\DependencyInjection\CommandFilterCompilerPass;
use Fight\Common\Adapter\Messaging\Command\Sync\CommandPipeline;
use Fight\Common\Adapter\ServiceContainer\Symfony\CommandFilterCompilerPass as CanonicalCommandFilterCompilerPass;
use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(CommandFilterCompilerPass::class)]
#[CoversClass(CanonicalCommandFilterCompilerPass::class)]
class CommandFilterCompilerPassTest extends UnitTestCase
{
    public function test_that_canonical_and_legacy_identities_register_command_filters(): void
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
            foreach ([CanonicalCommandFilterCompilerPass::class, CommandFilterCompilerPass::class] as $passClass) {
                $container = new ContainerBuilder();
                $pipeline = $container->register(CommandPipeline::class, CommandPipeline::class);
                $container->register('filter_id', StubCommandFilter::class)->addTag('common.command_filter');
                $container->addCompilerPass(new $passClass());
                $container->compile();

                $calls = $pipeline->getMethodCalls();
                self::assertCount(1, $calls);
                self::assertSame('addFilter', $calls[0][0]);
                self::assertSame('filter_id', (string) $calls[0][1][0]);
            }
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
    }

    public function test_that_it_returns_early_when_pipeline_not_registered(): void
    {
        $container = new ContainerBuilder();
        $pass = new CommandFilterCompilerPass();
        $pass->process($container);

        self::assertFalse($container->has(CommandPipeline::class));
    }

    public function test_that_it_registers_tagged_command_filters(): void
    {
        $container = new ContainerBuilder();
        $pipelineDef = $container->register(CommandPipeline::class, CommandPipeline::class);

        $filterDef = $container->register('filter_id', StubCommandFilter::class);
        $filterDef->addTag('common.command_filter', []);

        $pass = new CommandFilterCompilerPass();
        $pass->process($container);

        $calls = $pipelineDef->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('addFilter', $calls[0][0]);
        self::assertInstanceOf(Reference::class, $calls[0][1][0]);
        self::assertSame('filter_id', (string) $calls[0][1][0]);
    }

    public function test_that_it_registers_multiple_tagged_command_filters(): void
    {
        $container = new ContainerBuilder();
        $pipelineDef = $container->register(CommandPipeline::class, CommandPipeline::class);

        $filterDef1 = $container->register('filter_1', StubCommandFilter::class);
        $filterDef1->addTag('common.command_filter', []);

        $filterDef2 = $container->register('filter_2', StubOtherCommandFilter::class);
        $filterDef2->addTag('common.command_filter', []);

        $pass = new CommandFilterCompilerPass();
        $pass->process($container);

        $calls = $pipelineDef->getMethodCalls();
        self::assertCount(2, $calls);
        self::assertSame('addFilter', $calls[0][0]);
        self::assertSame('filter_1', (string) $calls[0][1][0]);
        self::assertSame('addFilter', $calls[1][0]);
        self::assertSame('filter_2', (string) $calls[1][1][0]);
    }

    public function test_that_it_throws_when_tagged_filter_does_not_implement_command_filter(): void
    {
        $container = new ContainerBuilder();
        $container->register(CommandPipeline::class, CommandPipeline::class);

        $badDef = $container->register('bad_filter', StubCmdFilterNonFilterService::class);
        $badDef->addTag('common.command_filter', []);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must implement interface');

        $pass = new CommandFilterCompilerPass();
        $pass->process($container);
    }
}

class StubCommandFilter implements CommandFilter
{
    public function process(CommandMessage $commandMessage, callable $next): void {}
}

class StubOtherCommandFilter implements CommandFilter
{
    public function process(CommandMessage $commandMessage, callable $next): void {}
}

class StubCmdFilterNonFilterService
{
}
