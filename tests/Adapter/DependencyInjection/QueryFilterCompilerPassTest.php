<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\DependencyInjection;

use Exception;
use Fight\Common\Adapter\DependencyInjection\QueryFilterCompilerPass;
use Fight\Common\Adapter\Messaging\Query\QueryPipeline;
use Fight\Common\Application\Messaging\Query\QueryFilter;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(QueryFilterCompilerPass::class)]
class QueryFilterCompilerPassTest extends UnitTestCase
{
    public function test_that_it_returns_early_when_pipeline_not_registered(): void
    {
        $container = new ContainerBuilder();
        $pass = new QueryFilterCompilerPass();
        $pass->process($container);

        self::assertFalse($container->has(QueryPipeline::class));
    }

    public function test_that_it_registers_tagged_query_filters(): void
    {
        $container = new ContainerBuilder();
        $pipelineDef = $container->register(QueryPipeline::class, QueryPipeline::class);

        $filterDef = $container->register('filter_id', StubQueryFilter::class);
        $filterDef->addTag('common.query_filter', []);

        $pass = new QueryFilterCompilerPass();
        $pass->process($container);

        $calls = $pipelineDef->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('addFilter', $calls[0][0]);
        self::assertInstanceOf(Reference::class, $calls[0][1][0]);
        self::assertSame('filter_id', (string) $calls[0][1][0]);
    }

    public function test_that_it_registers_multiple_tagged_query_filters(): void
    {
        $container = new ContainerBuilder();
        $pipelineDef = $container->register(QueryPipeline::class, QueryPipeline::class);

        $filterDef1 = $container->register('filter_1', StubQueryFilter::class);
        $filterDef1->addTag('common.query_filter', []);

        $filterDef2 = $container->register('filter_2', StubOtherQueryFilter::class);
        $filterDef2->addTag('common.query_filter', []);

        $pass = new QueryFilterCompilerPass();
        $pass->process($container);

        $calls = $pipelineDef->getMethodCalls();
        self::assertCount(2, $calls);
        self::assertSame('addFilter', $calls[0][0]);
        self::assertSame('filter_1', (string) $calls[0][1][0]);
        self::assertSame('addFilter', $calls[1][0]);
        self::assertSame('filter_2', (string) $calls[1][1][0]);
    }

    public function test_that_it_throws_when_tagged_filter_does_not_implement_query_filter(): void
    {
        $container = new ContainerBuilder();
        $container->register(QueryPipeline::class, QueryPipeline::class);

        $badDef = $container->register('bad_filter', StubQryFilterNonFilterService::class);
        $badDef->addTag('common.query_filter', []);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must implement interface');

        $pass = new QueryFilterCompilerPass();
        $pass->process($container);
    }
}

class StubQueryFilter implements QueryFilter
{
    public function process(QueryMessage $queryMessage, callable $next): void {}
}

class StubOtherQueryFilter implements QueryFilter
{
    public function process(QueryMessage $queryMessage, callable $next): void {}
}

class StubQryFilterNonFilterService
{
}
