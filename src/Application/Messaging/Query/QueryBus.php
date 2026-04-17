<?php

declare(strict_types=1);

namespace Fight\Common\Application\Messaging\Query;

use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Throwable;

/**
 * Interface QueryBus
 */
interface QueryBus
{
    /**
     * Fetches query results
     *
     * @throws Throwable When an error occurs
     */
    public function fetch(Query $query): mixed;

    /**
     * Dispatches a query message
     *
     * @throws Throwable When an error occurs
     */
    public function dispatch(QueryMessage $queryMessage): mixed;
}
