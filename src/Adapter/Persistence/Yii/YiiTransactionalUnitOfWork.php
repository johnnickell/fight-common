<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Yii;

use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use LogicException;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Transaction\TransactionInterface;

/**
 * Class YiiTransactionalUnitOfWork
 */
final class YiiTransactionalUnitOfWork implements TransactionalUnitOfWork
{
    private bool $connectionHasBeenEstablished;

    /**
     * Constructs YiiTransactionalUnitOfWork
     */
    public function __construct(private readonly ConnectionInterface $connection)
    {
        $this->connectionHasBeenEstablished = $this->connection->isActive();
    }

    /**
     * @inheritDoc
     */
    public function commitTransactional(callable $operation): mixed
    {
        if ($this->isClosed()) {
            throw new LogicException('Transactional execution is not supported on a closed connection.');
        }

        if ($this->connection->getTransaction() instanceof TransactionInterface) {
            throw new LogicException('Nested transactional execution is not supported.');
        }

        $this->connectionHasBeenEstablished = true;

        return $this->connection->transaction(static fn (): mixed => $operation());
    }

    /**
     * @inheritDoc
     */
    public function isClosed(): bool
    {
        if ($this->connection->isActive()) {
            $this->connectionHasBeenEstablished = true;

            return false;
        }

        return $this->connectionHasBeenEstablished;
    }
}
