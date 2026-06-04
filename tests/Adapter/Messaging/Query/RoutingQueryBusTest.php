<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Query;

use Fight\Common\Adapter\Messaging\Query\Routing\QueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RoutingQueryBus::class)]
class RoutingQueryBusTest extends UnitTestCase
{
    public function test_that_fetch_wraps_query_and_dispatches_to_handler(): void
    {
        $expected = ['items' => []];

        /** @var MockInterface|QueryHandler $handler */
        $handler = $this->mock(QueryHandler::class);
        $handler->shouldReceive('handle')
            ->withArgs(fn(QueryMessage $msg): bool => $msg->payload() instanceof SampleRoutingQuery)
            ->andReturn($expected);

        /** @var MockInterface|QueryRouter $router */
        $router = $this->mock(QueryRouter::class);
        $router->shouldReceive('match')->andReturn($handler);

        $bus = new RoutingQueryBus($router);

        self::assertSame($expected, $bus->fetch(new SampleRoutingQuery()));
    }

    public function test_that_dispatch_routes_message_to_matched_handler(): void
    {
        $query = new SampleRoutingQuery();
        $message = QueryMessage::create($query);
        $expected = 'result';

        /** @var MockInterface|QueryHandler $handler */
        $handler = $this->mock(QueryHandler::class);
        $handler->shouldReceive('handle')->with($message)->andReturn($expected);

        /** @var MockInterface|QueryRouter $router */
        $router = $this->mock(QueryRouter::class);
        $router->shouldReceive('match')->with($query)->andReturn($handler);

        $bus = new RoutingQueryBus($router);

        self::assertSame($expected, $bus->dispatch($message));
    }
}

class SampleRoutingQuery implements Query
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
