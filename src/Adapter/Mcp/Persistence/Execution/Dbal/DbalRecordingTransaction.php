<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTransactionalUnitOfWork;
use Fight\Common\Adapter\Persistence\Doctrine\Exception\DbalTransactionOutcomeUncertain;
use Fight\Common\Application\Mcp\Observation\Exception\ObservationStorageFailed;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Class DbalRecordingTransaction
 *
 * Owns execution schema serialization and failure translation around the reusable DBAL boundary
 */
final class DbalRecordingTransaction implements TransactionalUnitOfWork
{
    private bool $serialized = false;
    private readonly DbalTransactionalUnitOfWork $transactionalUnitOfWork;

    /**
     * Constructs DbalRecordingTransaction
     */
    public function __construct(private readonly Connection $connection)
    {
        $this->transactionalUnitOfWork = new DbalTransactionalUnitOfWork($connection);
        $platform = $connection->getDatabasePlatform();
        if (
            !$platform instanceof SQLitePlatform && !$platform instanceof AbstractMySQLPlatform
            && !$platform instanceof PostgreSQLPlatform
        ) {
            throw new InvalidArgumentException('Observation storage supports SQLite, MySQL and PostgreSQL only.');
        }

        if ($platform instanceof SQLitePlatform) {
            if ($connection->isTransactionActive()) {
                throw new LogicException('Observation storage requires an idle connection at construction.');
            }

            $connection->executeStatement('PRAGMA foreign_keys = ON');
        }
    }

    /**
     * @inheritDoc
     */
    public function commitTransactional(callable $operation): mixed
    {
        if ($this->serialized) {
            throw new LogicException('Nested transactional execution is not supported.');
        }

        $operationStarted = false;
        try {
            return $this->transactionalUnitOfWork->commitTransactional(function () use (
                $operation,
                &$operationStarted
            ): mixed {
                $operationStarted = true;
                // Taking the same write lock before reads avoids snapshot/unique-key retry races.
                $this->connection->executeStatement(
                    'UPDATE fight_execution_schema SET version = version WHERE singleton = 1'
                );
                if (
                    (int) $this->connection->fetchOne(
                        'SELECT version FROM fight_execution_schema WHERE singleton = 1'
                    ) !== 1
                ) {
                    throw new RuntimeException('Unsupported observation storage version.');
                }

                $this->serialized = true;

                return $operation();
            });
        } catch (DbalTransactionOutcomeUncertain $exception) {
            throw new ObservationStorageFailed(true, $exception->originalFailure());
        } catch (Exception $exception) {
            if (!$operationStarted) {
                throw $exception;
            }

            throw new ObservationStorageFailed(false, $exception);
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
     * Returns the shared connection for participating DBAL repositories
     */
    public function connection(): Connection
    {
        return $this->connection;
    }

    /**
     * Validates write ownership rather than accepting arbitrary caller transactions
     */
    public function requireOwnedTransaction(): void
    {
        if (!$this->serialized || !$this->connection->isTransactionActive()) {
            throw new LogicException('Observation writes require the recording-owned transaction.');
        }
    }
}
