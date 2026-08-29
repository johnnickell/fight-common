<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\CodeIgniter;

use CodeIgniter\Database\BaseConnection;
use Fight\Common\Adapter\Persistence\CodeIgniter\CodeIgniterTransactionalUnitOfWork;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;

/**
 * Class PersistenceServices
 *
 * A project-owned Config\Services class selects this capability and owns its
 * database connection selection and lifecycle policy.
 */
final class PersistenceServices
{
    // phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
    /**
     * Creates the native transactional unit of work
     *
     * @param BaseConnection<mixed, mixed> $connection
     */
    public static function codeIgniterTransactionalUnitOfWork(
        BaseConnection $connection
    ): CodeIgniterTransactionalUnitOfWork {
        return new CodeIgniterTransactionalUnitOfWork($connection);
    }

    /**
     * Creates the transactional unit of work through its application contract
     *
     * @param BaseConnection<mixed, mixed> $connection
     */
    public static function transactionalUnitOfWork(
        BaseConnection $connection
    ): TransactionalUnitOfWork {
        return self::codeIgniterTransactionalUnitOfWork($connection);
    }
    // phpcs:enable Squiz.Commenting.FunctionComment.IncorrectTypeHint
}
