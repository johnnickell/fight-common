<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Query;

use Fight\Common\Adapter\Messaging\Query\Routing\QueryRouter;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;

/**
 * Class RoutingQueryBus
 */
final readonly class RoutingQueryBus implements QueryBus
{
    /**
     * Constructs RoutingQueryBus
     */
    public function __construct(private QueryRouter $queryRouter)
    {
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
        /** @var Query $query */
        $query = $queryMessage->payload();

        return $this->queryRouter->match($query)->handle($queryMessage);
    }
}
