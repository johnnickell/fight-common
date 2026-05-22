<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Query;

use Fight\Common\Adapter\Messaging\Query\QueryPipeline;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Messaging\Query\QueryFilter;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(QueryPipeline::class)]
class QueryPipelineTest extends UnitTestCase
{
    public function test_that_fetch_wraps_query_and_dispatches(): void
    {
        $result = ['data' => 'value'];

        /** @var MockInterface|QueryBus $bus */
        $bus = $this->mock(QueryBus::class);
        $bus->shouldReceive('fetch')->andReturn($result);

        $pipeline = new QueryPipeline($bus);

        self::assertSame($result, $pipeline->fetch(new SamplePipelineQuery()));
    }

    public function test_that_dispatch_pipes_message_and_returns_results(): void
    {
        $result = 'some result';

        /** @var MockInterface|QueryBus $bus */
        $bus = $this->mock(QueryBus::class);
        $bus->shouldReceive('fetch')->andReturn($result);

        $pipeline = new QueryPipeline($bus);
        $actual = $pipeline->dispatch(QueryMessage::create(new SamplePipelineQuery()));

        self::assertSame($result, $actual);
    }

    public function test_that_dispatch_resets_results_after_returning(): void
    {
        /** @var MockInterface|QueryBus $bus */
        $bus = $this->mock(QueryBus::class);
        $bus->shouldReceive('fetch')->andReturn('result');

        $pipeline = new QueryPipeline($bus);
        $pipeline->dispatch(QueryMessage::create(new SamplePipelineQuery()));

        $second = $pipeline->dispatch(QueryMessage::create(new SamplePipelineQuery()));

        self::assertSame('result', $second);
    }

    public function test_that_process_calls_inner_bus_fetch(): void
    {
        $result = 'fetched';

        /** @var MockInterface|QueryBus $bus */
        $bus = $this->mock(QueryBus::class);
        $bus->shouldReceive('fetch')
            ->once()
            ->withArgs(fn(Query $q): bool => $q instanceof SamplePipelineQuery)
            ->andReturn($result);

        $pipeline = new QueryPipeline($bus);
        $message = QueryMessage::create(new SamplePipelineQuery());
        $pipeline->process($message, function (): void {});
        self::assertTrue(true);
    }

    public function test_that_add_filter_is_called_before_inner_bus(): void
    {
        $calls = [];

        /** @var MockInterface|QueryBus $bus */
        $bus = $this->mock(QueryBus::class);
        $bus->shouldReceive('fetch')->andReturnUsing(function () use (&$calls): string {
            $calls[] = 'bus';
            return 'result';
        });

        $filter = new class ($calls) implements QueryFilter {
            public function __construct(private array &$calls) {}

            public function process(QueryMessage $queryMessage, callable $next): void
            {
                $this->calls[] = 'filter';
                $next($queryMessage);
            }
        };

        $pipeline = new QueryPipeline($bus);
        $pipeline->addFilter($filter);
        $pipeline->dispatch(QueryMessage::create(new SamplePipelineQuery()));

        self::assertSame(['filter', 'bus'], $calls);
    }

    public function test_that_multiple_filters_execute_in_lifo_order(): void
    {
        $calls = [];

        /** @var MockInterface|QueryBus $bus */
        $bus = $this->mock(QueryBus::class);
        $bus->shouldReceive('fetch')->andReturnUsing(function () use (&$calls): string {
            $calls[] = 'bus';
            return 'result';
        });

        $makeFilter = function (string $name) use (&$calls): QueryFilter {
            return new class ($name, $calls) implements QueryFilter {
                public function __construct(private readonly string $name, private array &$calls) {}

                public function process(QueryMessage $queryMessage, callable $next): void
                {
                    $this->calls[] = $this->name;
                    $next($queryMessage);
                }
            };
        };

        $pipeline = new QueryPipeline($bus);
        $pipeline->addFilter($makeFilter('first'));
        $pipeline->addFilter($makeFilter('second'));
        $pipeline->dispatch(QueryMessage::create(new SamplePipelineQuery()));

        self::assertSame(['second', 'first', 'bus'], $calls);
    }
}

class SamplePipelineQuery implements Query
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
