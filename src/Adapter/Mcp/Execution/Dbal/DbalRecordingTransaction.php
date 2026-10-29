<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Execution\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Fight\Common\Application\Mcp\Execution\Observation\Exception\ObservationStorageFailed;
use Fight\Common\Application\Mcp\Execution\RecordingTransaction;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Class DbalRecordingTransaction
 */
final class DbalRecordingTransaction implements RecordingTransaction
{
    private bool $owned = false;
    private bool $closed = false;

    /**
     * Constructs DbalRecordingTransaction
     */
    public function __construct(private readonly Connection $connection)
    {
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
        if ($this->closed || $this->owned || $this->connection->isTransactionActive()) {
            throw new LogicException('Observation recording requires its own real transaction on an idle store.');
        }

        try {
            $this->connection->beginTransaction();
        } catch (Throwable $throwable) {
            $this->closed = true;
            throw new ObservationStorageFailed(true, $throwable);
        }

        $this->owned = true;
        try {
            try {
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

                $result = $operation();
            } catch (Throwable $exception) {
                $this->rollback($exception);
                if ($exception instanceof Exception) {
                    throw new ObservationStorageFailed(false, $exception);
                }

                throw $exception;
            }

            try {
                $this->connection->commit();
            } catch (Throwable $exception) {
                $this->closed = true;
                // A lost commit acknowledgment is not proof of rollback, even if cleanup succeeds.
                if ($this->connection->isTransactionActive()) {
                    $this->rollback($exception);
                }

                throw new ObservationStorageFailed(true, $exception);
            }

            return $result;
        } finally {
            $this->owned = false;
        }
    }

    /**
     * @inheritDoc
     */
    public function isClosed(): bool
    {
        return $this->closed;
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
        if (!$this->owned || !$this->connection->isTransactionActive()) {
            throw new LogicException('Observation writes require the recording-owned transaction.');
        }
    }

    /**
     * Handles rollback of only this boundary's transaction or reports uncertainty
     */
    private function rollback(Throwable $original): void
    {
        try {
            $this->connection->rollBack();
        } catch (Throwable) {
            $this->closed = true;
            throw new ObservationStorageFailed(true, $original);
        }
    }
}
