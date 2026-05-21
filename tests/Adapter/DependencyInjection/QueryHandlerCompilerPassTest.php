<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\DependencyInjection;

use Exception;
use Fight\Common\Adapter\DependencyInjection\QueryHandlerCompilerPass;
use Fight\Common\Adapter\Messaging\Query\Routing\ServiceAwareQueryRouter;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(QueryHandlerCompilerPass::class)]
class QueryHandlerCompilerPassTest extends UnitTestCase
{
    public function test_that_it_returns_early_when_router_not_registered(): void
    {
        $container = new ContainerBuilder();
        $pass = new QueryHandlerCompilerPass();
        $pass->process($container);

        self::assertFalse($container->has(ServiceAwareQueryRouter::class));
    }

    public function test_that_it_registers_tagged_query_handlers(): void
    {
        $container = new ContainerBuilder();
        $routerDef = $container->register(ServiceAwareQueryRouter::class, ServiceAwareQueryRouter::class);

        $handlerDef = $container->register('handler_id', StubQueryHandler::class);
        $handlerDef->addTag('common.query_handler', []);
        $handlerDef->setPublic(true);

        $pass = new QueryHandlerCompilerPass();
        $pass->process($container);

        $calls = $routerDef->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('registerHandler', $calls[0][0]);
        self::assertSame([StubQuery::class, 'handler_id'], $calls[0][1]);
    }

    public function test_that_it_registers_multiple_tagged_query_handlers(): void
    {
        $container = new ContainerBuilder();
        $routerDef = $container->register(ServiceAwareQueryRouter::class, ServiceAwareQueryRouter::class);

        $handlerDef1 = $container->register('handler_1', StubQueryHandler::class);
        $handlerDef1->addTag('common.query_handler', []);
        $handlerDef1->setPublic(true);

        $handlerDef2 = $container->register('handler_2', StubOtherQueryHandler::class);
        $handlerDef2->addTag('common.query_handler', []);
        $handlerDef2->setPublic(true);

        $pass = new QueryHandlerCompilerPass();
        $pass->process($container);

        $calls = $routerDef->getMethodCalls();
        self::assertCount(2, $calls);
        self::assertSame('registerHandler', $calls[0][0]);
        self::assertSame([StubQuery::class, 'handler_1'], $calls[0][1]);
        self::assertSame('registerHandler', $calls[1][0]);
        self::assertSame([StubOtherQuery::class, 'handler_2'], $calls[1][1]);
    }

    public function test_that_it_throws_when_tagged_handler_is_not_public(): void
    {
        $container = new ContainerBuilder();
        $container->register(ServiceAwareQueryRouter::class, ServiceAwareQueryRouter::class);

        $handlerDef = $container->register('private_handler', StubQueryHandler::class);
        $handlerDef->addTag('common.query_handler', []);
        $handlerDef->setPublic(false);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must be public');

        $pass = new QueryHandlerCompilerPass();
        $pass->process($container);
    }

    public function test_that_it_throws_when_tagged_handler_does_not_implement_query_handler(): void
    {
        $container = new ContainerBuilder();
        $container->register(ServiceAwareQueryRouter::class, ServiceAwareQueryRouter::class);

        $badDef = $container->register('bad_handler', StubQueryNonHandlerService::class);
        $badDef->addTag('common.query_handler', []);
        $badDef->setPublic(true);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must implement interface');

        $pass = new QueryHandlerCompilerPass();
        $pass->process($container);
    }
}

class StubQuery implements Query
{
    public static function fromArray(array $data): static
    {
        return new static();
    }

    public function toArray(): array
    {
        return [];
    }
}

class StubOtherQuery implements Query
{
    public static function fromArray(array $data): static
    {
        return new static();
    }

    public function toArray(): array
    {
        return [];
    }
}

class StubQueryHandler implements QueryHandler
{
    public static function queryRegistration(): string
    {
        return StubQuery::class;
    }

    public function handle(QueryMessage $queryMessage): mixed
    {
        return null;
    }
}

class StubOtherQueryHandler implements QueryHandler
{
    public static function queryRegistration(): string
    {
        return StubOtherQuery::class;
    }

    public function handle(QueryMessage $queryMessage): mixed
    {
        return null;
    }
}

class StubQueryNonHandlerService
{
}
