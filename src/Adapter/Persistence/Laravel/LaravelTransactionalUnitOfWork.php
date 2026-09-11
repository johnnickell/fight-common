<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Laravel;

use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Illuminate\Database\Connection;
use LogicException;

/**
 * Class LaravelTransactionalUnitOfWork
 */
final readonly class LaravelTransactionalUnitOfWork implements TransactionalUnitOfWork
{
    /**
     * Constructs LaravelTransactionalUnitOfWork
     */
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @inheritDoc
     */
    public function commitTransactional(callable $operation): mixed
    {
        if ($this->connection->transactionLevel() > 0) {
            throw new LogicException('Nested transactional execution is not supported.');
        }

        return $this->connection->transaction(static fn(): mixed => $operation());
    }

    /**
     * @inheritDoc
     */
    public function isClosed(): bool
    {
        return $this->connection->getRawPdo() === null;
    }
}
