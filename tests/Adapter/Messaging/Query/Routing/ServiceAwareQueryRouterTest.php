<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Query\Routing;

use Fight\Common\Adapter\Messaging\Query\Routing\ServiceAwareQueryRouter;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Exception\LookupException;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Container\ContainerInterface;

#[CoversClass(ServiceAwareQueryRouter::class)]
class ServiceAwareQueryRouterTest extends UnitTestCase
{
    /** @var MockInterface|ContainerInterface */
    private ContainerInterface $container;

    private ServiceAwareQueryRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = $this->mock(ContainerInterface::class);
        $this->router = new ServiceAwareQueryRouter($this->container);
    }

    public function test_that_register_handler_and_get_handler_load_from_container(): void
    {
        $handler = new SampleServiceQueryHandler();

        $this->container->shouldReceive('has')->with('handler_service')->andReturn(true);
        $this->container->shouldReceive('get')->with('handler_service')->andReturn($handler);

        $this->router->registerHandler(SampleServiceQuery::class, 'handler_service');

        self::assertSame($handler, $this->router->getHandler(SampleServiceQuery::class));
    }

    public function test_that_register_handlers_batch_registers_multiple(): void
    {
        $handler1 = new SampleServiceQueryHandler();
        $handler2 = new SampleOtherServiceQueryHandler();

        $this->container->shouldReceive('has')->andReturn(true);
        $this->container->shouldReceive('get')->with('svc1')->andReturn($handler1);
        $this->container->shouldReceive('get')->with('svc2')->andReturn($handler2);

        $this->router->registerHandlers([
            SampleServiceQuery::class => 'svc1',
            SampleOtherServiceQuery::class => 'svc2',
        ]);

        self::assertSame($handler1, $this->router->getHandler(SampleServiceQuery::class));
        self::assertSame($handler2, $this->router->getHandler(SampleOtherServiceQuery::class));
    }

    public function test_that_match_delegates_to_get_handler(): void
    {
        $handler = new SampleServiceQueryHandler();

        $this->container->shouldReceive('has')->andReturn(true);
        $this->container->shouldReceive('get')->with('svc')->andReturn($handler);

        $this->router->registerHandler(SampleServiceQuery::class, 'svc');

        self::assertSame($handler, $this->router->match(new SampleServiceQuery()));
    }

    public function test_that_get_handler_throws_when_not_registered(): void
    {
        $this->container->shouldReceive('has')->andReturn(false);

        $this->expectException(LookupException::class);
        $this->router->getHandler(SampleServiceQuery::class);
    }

    public function test_that_has_handler_returns_false_when_not_registered(): void
    {
        self::assertFalse($this->router->hasHandler(SampleServiceQuery::class));
    }

    public function test_that_has_handler_checks_container_has(): void
    {
        $this->container->shouldReceive('has')->with('svc')->andReturn(true);

        $this->router->registerHandler(SampleServiceQuery::class, 'svc');

        self::assertTrue($this->router->hasHandler(SampleServiceQuery::class));
    }

    public function test_that_has_handler_returns_false_when_container_does_not_have_service(): void
    {
        $this->container->shouldReceive('has')->with('svc')->andReturn(false);

        $this->router->registerHandler(SampleServiceQuery::class, 'svc');

        self::assertFalse($this->router->hasHandler(SampleServiceQuery::class));
    }
}

class SampleServiceQuery implements Query
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

class SampleOtherServiceQuery implements Query
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

class SampleServiceQueryHandler implements QueryHandler
{
    public static function queryRegistration(): string
    {
        return SampleServiceQuery::class;
    }

    public function handle(QueryMessage $queryMessage): mixed
    {
        return null;
    }
}

class SampleOtherServiceQueryHandler implements QueryHandler
{
    public static function queryRegistration(): string
    {
        return SampleOtherServiceQuery::class;
    }

    public function handle(QueryMessage $queryMessage): mixed
    {
        return null;
    }
}
