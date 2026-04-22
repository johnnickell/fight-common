<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Query;

use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Messaging\Query\QueryFilter;
use Fight\Common\Domain\Collection\LinkedStack;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Throwable;

/**
 * Class QueryPipeline
 */
final class QueryPipeline implements QueryBus, QueryFilter
{
    private readonly LinkedStack $filters;
    private ?LinkedStack $executionStack = null;
    private mixed $results;

    /**
     * Constructs QueryPipeline
     */
    public function __construct(private readonly QueryBus $queryBus)
    {
        $this->filters = LinkedStack::of(QueryFilter::class);
        $this->filters->push($this);
    }

    /**
     * Adds a query filter to the pipeline
     */
    public function addFilter(QueryFilter $filter): void
    {
        $this->filters->push($filter);
    }

    /**
     * @inheritDoc
     */
    public function fetch(Query $query): mixed
    {
        return $this->dispatch(QueryMessage::create($query));
    }

    /**
     * @inheritDoc
     */
    public function dispatch(QueryMessage $queryMessage): mixed
    {
        $this->executionStack = clone $this->filters;
        $this->pipe($queryMessage);

        $results = $this->results;
        $this->results = null;

        return $results;
    }

    /**
     * @inheritDoc
     */
    public function process(QueryMessage $queryMessage, callable $next): void
    {
        /** @var Query $query */
        $query = $queryMessage->payload();
        $this->results = $this->queryBus->fetch($query);
    }

    /**
     * Pipes query message to the next filter
     *
     * @throws Throwable
     */
    public function pipe(QueryMessage $queryMessage): void
    {
        /** @var QueryFilter $filter */
        $filter = $this->executionStack->pop();
        $filter->process($queryMessage, $this->pipe(...));
    }
}
