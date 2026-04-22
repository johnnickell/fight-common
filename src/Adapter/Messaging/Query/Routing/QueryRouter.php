<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Query\Routing;

use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Exception\LookupException;
use Fight\Common\Domain\Messaging\Query\Query;

/**
 * Interface QueryRouter
 */
interface QueryRouter
{
    /**
     * Matches a Query to a handler
     *
     * @throws LookupException When the handler is not found
     */
    public function match(Query $query): QueryHandler;
}
