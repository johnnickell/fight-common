<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Query\Routing;

use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Exception\LookupException;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryQueryRouter::class)]
class InMemoryQueryRouterTest extends UnitTestCase
{
    private InMemoryQueryRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new InMemoryQueryRouter();
    }

    public function test_that_register_handler_and_match_return_correct_handler(): void
    {
        $query = new SampleInMemoryQuery();
        $handler = new SampleInMemoryQueryHandler();

        $this->router->registerHandler(SampleInMemoryQuery::class, $handler);

        self::assertSame($handler, $this->router->match($query));
    }

    public function test_that_register_handlers_batch_registers_multiple(): void
    {
        $handler1 = new SampleInMemoryQueryHandler();
        $handler2 = new SampleOtherInMemoryQueryHandler();

        $this->router->registerHandlers([
            SampleInMemoryQuery::class => $handler1,
            SampleOtherInMemoryQuery::class => $handler2,
        ]);

        self::assertSame($handler1, $this->router->match(new SampleInMemoryQuery()));
        self::assertSame($handler2, $this->router->match(new SampleOtherInMemoryQuery()));
    }

    public function test_that_get_handler_returns_registered_handler(): void
    {
        $handler = new SampleInMemoryQueryHandler();
        $this->router->registerHandler(SampleInMemoryQuery::class, $handler);

        self::assertSame($handler, $this->router->getHandler(SampleInMemoryQuery::class));
    }

    public function test_that_get_handler_throws_lookup_exception_when_not_found(): void
    {
        $this->expectException(LookupException::class);
        $this->router->getHandler(SampleInMemoryQuery::class);
    }

    public function test_that_has_handler_returns_false_when_not_registered(): void
    {
        self::assertFalse($this->router->hasHandler(SampleInMemoryQuery::class));
    }

    public function test_that_has_handler_returns_true_when_registered(): void
    {
        $this->router->registerHandler(SampleInMemoryQuery::class, new SampleInMemoryQueryHandler());

        self::assertTrue($this->router->hasHandler(SampleInMemoryQuery::class));
    }
}

class SampleInMemoryQuery implements Query
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

class SampleOtherInMemoryQuery implements Query
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

class SampleInMemoryQueryHandler implements QueryHandler
{
    public static function queryRegistration(): string
    {
        return SampleInMemoryQuery::class;
    }

    public function handle(QueryMessage $queryMessage): mixed
    {
        return null;
    }
}

class SampleOtherInMemoryQueryHandler implements QueryHandler
{
    public static function queryRegistration(): string
    {
        return SampleOtherInMemoryQuery::class;
    }

    public function handle(QueryMessage $queryMessage): mixed
    {
        return null;
    }
}
