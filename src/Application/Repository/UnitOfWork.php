<?php

declare(strict_types=1);

namespace Fight\Common\Application\Repository;

use Exception;

/**
 * Interface UnitOfWork
 */
interface UnitOfWork extends TransactionalUnitOfWork
{
    /**
     * Persists pending changes in the underlying persistence context
     *
     * @deprecated Use commitTransactional() for new consumers.
     *
     * @throws Exception When an error occurs
     */
    public function commit(): void;
}
