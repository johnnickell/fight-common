<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\TransactionIsolationLevel;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTransactionalUnitOfWork;
use Fight\Common\Adapter\Persistence\Doctrine\Exception\DbalTransactionOutcomeUncertain;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeStorageFailed;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Class DbalNoticeTransaction
 *
 * Owns notice namespace serialization and failure translation around the reusable DBAL boundary
 */
final class DbalNoticeTransaction implements TransactionalUnitOfWork
{
    private bool $serialized = false;
    private readonly DbalTransactionalUnitOfWork $transactionalUnitOfWork;

    /**
     * Constructs DbalNoticeTransaction
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly DbalNoticeTables $tables = new DbalNoticeTables()
    ) {
        $this->transactionalUnitOfWork = new DbalTransactionalUnitOfWork($connection);
        $platform = $connection->getDatabasePlatform();
        if (
            !$platform instanceof SQLitePlatform && !$platform instanceof AbstractMySQLPlatform
            && !$platform instanceof PostgreSQLPlatform
        ) {
            throw new InvalidArgumentException('Notice storage supports SQLite, MySQL and PostgreSQL only.');
        }

        if ($connection->isTransactionActive()) {
            throw new LogicException('Notice storage requires an idle connection at construction.');
        }

        if ($platform instanceof SQLitePlatform) {
            $connection->executeStatement('PRAGMA foreign_keys = ON');
        }

        $this->requireCommittedIsolation();
    }

    /**
     * @inheritDoc
     */
    public function commitTransactional(callable $operation): mixed
    {
        if ($this->serialized) {
            throw new LogicException('Nested transactional execution is not supported.');
        }

        $this->requireCommittedIsolation();
        $operationStarted = false;
        try {
            return $this->transactionalUnitOfWork->commitTransactional(function () use (
                $operation,
                &$operationStarted
            ): mixed {
                $operationStarted = true;
                $stateTable = $this->tables->stateTable()->quoted($this->connection->getDatabasePlatform());
                // Serialize before identity reads and hold allocation and loss evidence through actual commit.
                $this->connection->executeStatement(
                    'UPDATE '.$stateTable.' SET last_position = last_position WHERE singleton = 1'
                );
                if (
                    (int) $this->connection->fetchOne(
                        'SELECT version FROM '.$stateTable.' WHERE singleton = 1'
                    ) !== 1
                ) {
                    throw new RuntimeException('Unsupported notice schema.');
                }

                $this->serialized = true;
                $result = $operation();
                $this->requireOwnedTransaction();

                return $result;
            });
        } catch (DbalTransactionOutcomeUncertain $exception) {
            throw new NoticeStorageFailed(true, $exception->originalFailure());
        } catch (Exception $exception) {
            if (!$operationStarted) {
                throw $exception;
            }

            throw new NoticeStorageFailed(false, $exception);
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
     * Returns the exact connection for participating notice repositories
     */
    public function connection(): Connection
    {
        return $this->connection;
    }

    /**
     * Returns the exact selected tables for participating notice repositories
     */
    public function tables(): DbalNoticeTables
    {
        return $this->tables;
    }

    /**
     * Validates serialization ownership rather than accepting unrelated transactions or savepoints
     */
    public function requireOwnedTransaction(): void
    {
        if (!$this->serialized || $this->connection->getTransactionNestingLevel() !== 1) {
            throw new LogicException('Notice writes require the notice-owned transaction.');
        }
    }

    /**
     * Validates isolation sufficient to exclude uncommitted recovery positions
     */
    private function requireCommittedIsolation(): void
    {
        if ($this->connection->getTransactionIsolation() === TransactionIsolationLevel::READ_UNCOMMITTED) {
            throw new LogicException('Notice storage does not support dirty reads.');
        }
    }
}
