<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Task\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\TransactionIsolationLevel;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTransactionalUnitOfWork;
use Fight\Common\Adapter\Persistence\Doctrine\Exception\DbalTransactionOutcomeUncertain;
use Fight\Common\Application\Mcp\Task\Exception\TaskStorageFailed;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Class DbalTaskTransaction
 *
 * Owns Task namespace serialization and failure translation around the reusable DBAL boundary
 */
final class DbalTaskTransaction implements TransactionalUnitOfWork
{
    private bool $serialized = false;
    private readonly DbalTransactionalUnitOfWork $transactionalUnitOfWork;

    /**
     * Constructs DbalTaskTransaction
     */
    public function __construct(private readonly Connection $connection)
    {
        $this->transactionalUnitOfWork = new DbalTransactionalUnitOfWork($connection);
        $platform = $connection->getDatabasePlatform();
        if (
            !$platform instanceof SQLitePlatform && !$platform instanceof AbstractMySQLPlatform
            && !$platform instanceof PostgreSQLPlatform
        ) {
            throw new InvalidArgumentException('Task storage supports SQLite, MySQL and PostgreSQL only.');
        }

        $this->requireCommittedRead();
    }

    /**
     * @inheritDoc
     */
    public function commitTransactional(callable $operation): mixed
    {
        $this->requireCommittedRead();
        $operationStarted = false;
        try {
            return $this->transactionalUnitOfWork->commitTransactional(function () use (
                $operation,
                &$operationStarted
            ): mixed {
                $operationStarted = true;
                // Serialize before admission and every lookup, including repeats, through actual commit.
                $this->connection->executeStatement(
                    'UPDATE fight_task_state SET version = version WHERE singleton = 1'
                );
                if (
                    (int) $this->connection->fetchOne('SELECT version FROM fight_task_state WHERE singleton = 1') !== 1
                ) {
                    throw new RuntimeException('Unsupported Task schema.');
                }

                $this->serialized = true;
                $result = $operation();
                $this->requireOwnedTransaction();

                return $result;
            });
        } catch (DbalTransactionOutcomeUncertain $exception) {
            throw new TaskStorageFailed(true, $exception->originalFailure());
        } catch (Exception $exception) {
            if (!$operationStarted) {
                throw $exception;
            }

            throw new TaskStorageFailed(false, $exception);
        } finally {
            $this->serialized = false;
        }
    }

    /**
     * @inheritDoc
     */
    public function isClosed(): bool
    {
        return $this->transactionalUnitOfWork->isClosed();
    }

    /**
     * Returns the exact connection for participating Task persistence
     */
    public function connection(): Connection
    {
        return $this->connection;
    }

    /**
     * Validates namespace lock ownership rather than accepting an arbitrary active transaction
     */
    public function requireOwnedTransaction(): void
    {
        if (!$this->serialized || $this->connection->getTransactionNestingLevel() !== 1) {
            throw new LogicException('Task writes require the Task-owned transaction.');
        }
    }

    /**
     * Validates exclusive connection use and committed-read isolation
     */
    public function requireCommittedRead(): void
    {
        if ($this->isClosed() || $this->serialized || $this->connection->isTransactionActive()) {
            throw new LogicException('Task operations require an idle open boundary.');
        }

        if ($this->connection->getTransactionIsolation() === TransactionIsolationLevel::READ_UNCOMMITTED) {
            throw new LogicException('Task storage does not support dirty reads.');
        }
    }
}
