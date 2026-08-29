<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\CodeIgniter;

use CodeIgniter\Database\BaseConnection;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Class CodeIgniterTransactionalUnitOfWork
 */
final class CodeIgniterTransactionalUnitOfWork implements TransactionalUnitOfWork
{
    private bool $transactionActive = false;
    private bool $connectionHasBeenEstablished;

    /**
     * Constructs CodeIgniterTransactionalUnitOfWork
     *
     * @param BaseConnection $connection
     *
     * @phpstan-param BaseConnection<mixed, mixed> $connection
     */
    public function __construct(private readonly BaseConnection $connection)
    {
        $this->connectionHasBeenEstablished = $this->connection->getConnection() !== false;
    }

    /**
     * @inheritDoc
     */
    public function commitTransactional(callable $operation): mixed
    {
        if ($this->isClosed()) {
            throw new LogicException('Transactional execution is not supported on a closed connection.');
        }

        if ($this->transactionActive || $this->connection->transDepth > 0) {
            throw new LogicException('Nested transactional execution is not supported.');
        }

        $this->transactionActive = true;

        try {
            if (! $this->connection->transBegin()) {
                throw new RuntimeException('Could not begin CodeIgniter transaction.');
            }

            $this->connectionHasBeenEstablished = true;

            try {
                $result = $operation();
            } catch (Throwable $failure) {
                $this->rollback();

                throw $failure;
            }

            if (! $this->connection->transStatus()) {
                $this->rollback();

                throw new RuntimeException('CodeIgniter transaction failed.');
            }

            if (! $this->connection->transCommit()) {
                $this->rollback();

                throw new RuntimeException('Could not commit CodeIgniter transaction.');
            }

            return $result;
        } finally {
            $this->transactionActive = false;
        }
    }

    /**
     * @inheritDoc
     */
    public function isClosed(): bool
    {
        if ($this->connection->getConnection() !== false) {
            $this->connectionHasBeenEstablished = true;

            return false;
        }

        return $this->connectionHasBeenEstablished;
    }

    /**
     * Performs a rollback without masking a callback failure
     */
    private function rollback(): void
    {
        try {
            $this->connection->transRollback();
        } catch (Throwable) {
        }

        try {
            $this->connection->resetTransStatus();
        } catch (Throwable) {
        }
    }
}
