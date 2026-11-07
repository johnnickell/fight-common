<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Doctrine;

use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\Persistence\Doctrine\Exception\DbalTransactionOutcomeUncertain;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use LogicException;
use Throwable;

/**
 * Class DbalTransactionalUnitOfWork
 *
 * Owns a real DBAL transaction without an ORM flush or capability-specific locking
 */
final class DbalTransactionalUnitOfWork implements TransactionalUnitOfWork
{
    private bool $ownsTransaction = false;
    private bool $closed = false;

    /**
     * Constructs DbalTransactionalUnitOfWork
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @inheritDoc
     */
    public function commitTransactional(callable $operation): mixed
    {
        if ($this->closed) {
            throw new LogicException('The DBAL transactional unit of work is closed.');
        }

        if ($this->ownsTransaction || $this->connection->isTransactionActive()) {
            throw new LogicException('Nested transactional execution is not supported.');
        }

        // DBAL otherwise auto-starts an outer transaction while establishing a lazy connection.
        if (!$this->connection->isAutoCommit()) {
            throw new LogicException('DBAL auto-commit-off transaction composition is not supported.');
        }

        try {
            $this->connection->beginTransaction();
        } catch (Throwable $throwable) {
            $this->closed = true;
            throw new DbalTransactionOutcomeUncertain($throwable);
        }

        $this->ownsTransaction = true;
        try {
            try {
                $result = $operation();
            } catch (Throwable $throwable) {
                try {
                    $this->connection->rollBack();
                } catch (Throwable) {
                    $this->closed = true;
                    throw new DbalTransactionOutcomeUncertain($throwable);
                }

                throw $throwable;
            }

            try {
                $this->connection->commit();
            } catch (Throwable $throwable) {
                $this->closed = true;
                // Cleanup cannot turn a lost real commit acknowledgment into proof of rollback.
                try {
                    if ($this->connection->isTransactionActive()) {
                        $this->connection->rollBack();
                    }
                } catch (Throwable) {
                    // The commit failure remains the diagnostic cause of the uncertain outcome.
                }

                throw new DbalTransactionOutcomeUncertain($throwable);
            }

            return $result;
        } finally {
            $this->ownsTransaction = false;
        }
    }

    /**
     * @inheritDoc
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }
}
