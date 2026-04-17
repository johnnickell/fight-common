<?php

declare(strict_types=1);

namespace Fight\Common\Application\Messaging\Query;

use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Throwable;

/**
 * Interface QueryHandler
 */
interface QueryHandler
{
    /**
     * Retrieves query registration
     *
     * Returns the fully qualified class name for the query that this service
     * is meant to handle.
     */
    public static function queryRegistration(): string;

    /**
     * Handles a query
     *
     * @throws Throwable When an error occurs
     */
    public function handle(QueryMessage $queryMessage): mixed;
}
