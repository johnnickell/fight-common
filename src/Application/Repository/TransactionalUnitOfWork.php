<?php

declare(strict_types=1);

namespace Fight\Common\Application\Repository;

use Exception;

/**
 * Interface TransactionalUnitOfWork
 */
interface TransactionalUnitOfWork
{
    /**
     * Wraps an operation in a transaction and returns its result
     *
     * @throws Exception When an error occurs
     */
    public function commitTransactional(callable $operation): mixed;

    /**
     * Checks whether the unit of work is closed
     */
    public function isClosed(): bool;
}
