<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Task\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\TransactionIsolationLevel;
use Fight\Common\Application\Task\Exception\TaskStorageFailed;
use Fight\Common\Application\Task\Service\TaskStore;
use Fight\Common\Domain\Task\Task;
use Fight\Common\Domain\Task\TaskDefinition;
use Fight\Common\Domain\Task\TaskSelection;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Class DbalTaskStore
 */
final class DbalTaskStore implements TaskStore
{
    private bool $owned = false;
    private bool $closed = false;

    /**
     * Constructs DbalTaskStore
     */
    public function __construct(private readonly Connection $connection)
    {
        $platform = $connection->getDatabasePlatform();
        if (
            !$platform instanceof SQLitePlatform && !$platform instanceof AbstractMySQLPlatform
            && !$platform instanceof PostgreSQLPlatform
        ) {
            throw new InvalidArgumentException('Task storage supports SQLite, MySQL and PostgreSQL only.');
        }

        $this->requireIdle();
    }

    /**
     * @inheritDoc
     */
    public function commitTransactional(callable $operation): mixed
    {
        $this->requireIdle();
        try {
            $this->connection->beginTransaction();
        } catch (Throwable $throwable) {
            $this->closed = true;
            throw new TaskStorageFailed(true, $throwable);
        }

        $this->owned = true;
        try {
            try {
                // Serialize before any lookup, including repeats and future removal, through actual commit.
                $this->connection->executeStatement(
                    'UPDATE fight_task_state SET version = version WHERE singleton = 1'
                );
                if (
                    (int) $this->connection->fetchOne('SELECT version FROM fight_task_state WHERE singleton = 1') !== 1
                ) {
                    throw new RuntimeException('Unsupported Task schema.');
                }

                $result = $operation();
                $this->requireOwned();
            } catch (Throwable $cause) {
                $this->rollback($cause);
                if ($cause instanceof Exception) {
                    throw new TaskStorageFailed(false, $cause);
                }

                throw $cause;
            }

            try {
                $this->connection->commit();
            } catch (Throwable $cause) {
                $this->closed = true;
                if ($this->connection->isTransactionActive()) {
                    $this->rollback($cause);
                }

                throw new TaskStorageFailed(true, $cause);
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
     * @inheritDoc
     */
    public function getById(string $scope, string $id): ?Task
    {
        $this->requireOwned();

        return $this->load($scope, $id);
    }

    /**
     * @inheritDoc
     */
    public function save(Task $task): void
    {
        $this->requireOwned();
        $payload = json_encode($task->toArray(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        if (strlen($payload) > Task::MAX_STORED_BYTES) {
            throw new RuntimeException('Task exceeds the stored representation bound.');
        }

        $key = ['scope' => $task->definition()->scope(), 'task_id' => $task->definition()->id()];
        // The namespace lock makes replacement atomic across every supported backend without an upsert dialect.
        $this->connection->delete('fight_tasks', $key);
        $this->connection->insert('fight_tasks', [...$key, 'payload' => $payload]);
    }

    /**
     * @inheritDoc
     */
    public function remove(TaskSelection $selection): ?TaskSelection
    {
        $this->requireOwned();
        $removed = [];
        foreach ($selection->ids() as $id) {
            if ($this->connection->delete('fight_tasks', ['scope' => $selection->scope(), 'task_id' => $id]) === 1) {
                $removed[] = $id;
            }
        }

        return $removed === [] ? null : TaskSelection::fromIds($selection->scope(), $removed);
    }

    /**
     * @inheritDoc
     */
    public function read(string $scope, string $id): ?Task
    {
        $this->requireIdle();

        return $this->load($scope, $id);
    }

    /**
     * Validates actual transaction ownership rather than accepting a caller savepoint
     */
    private function requireOwned(): void
    {
        if (!$this->owned || $this->connection->getTransactionNestingLevel() !== 1) {
            throw new LogicException('Task writes require the store-owned transaction.');
        }
    }

    /**
     * Validates exclusive connection use and committed-read isolation
     */
    private function requireIdle(): void
    {
        if ($this->closed || $this->owned || $this->connection->isTransactionActive()) {
            throw new LogicException('Task operations require an idle open store.');
        }

        if ($this->connection->getTransactionIsolation() === TransactionIsolationLevel::READ_UNCOMMITTED) {
            throw new LogicException('Task storage does not support dirty reads.');
        }
    }

    /**
     * Returns one bounded coherent snapshot or an explicit storage failure
     */
    private function load(string $scope, string $id): ?Task
    {
        TaskDefinition::reference($scope);
        TaskDefinition::identity($id);
        try {
            $payload = $this->connection->fetchOne(
                'SELECT SUBSTRING(payload, 1, 40961) FROM fight_tasks WHERE scope = ? AND task_id = ?',
                [$scope, $id]
            );
            if ($payload === false) {
                return null;
            }

            if (!is_string($payload) || strlen($payload) > Task::MAX_STORED_BYTES) {
                throw new RuntimeException('Invalid stored Task size.');
            }

            $data = json_decode($payload, false, 96, JSON_THROW_ON_ERROR);
            // Object-mode decoding preserves empty objects and numeric object property names in protected payloads.
            if (
                !is_object($data) || !isset($data->definition, $data->state)
                || !is_object($data->definition) || !is_object($data->state)
            ) {
                throw new RuntimeException('Invalid stored Task.');
            }

            $data = get_object_vars($data);
            $data['definition'] = get_object_vars($data['definition']);
            $data['state'] = get_object_vars($data['state']);
            $task = Task::fromArray($data);
            if ($task->definition()->scope() !== $scope || $task->definition()->id() !== $id) {
                throw new RuntimeException('Inconsistent stored Task identity.');
            }

            return $task;
        } catch (Throwable $throwable) {
            throw new TaskStorageFailed(false, $throwable);
        }
    }

    /**
     * Handles rollback of only owned work without claiming certainty after cleanup failure
     */
    private function rollback(Throwable $original): void
    {
        try {
            $this->connection->rollBack();
        } catch (Throwable) {
            $this->closed = true;
            throw new TaskStorageFailed(true, $original);
        }
    }
}
