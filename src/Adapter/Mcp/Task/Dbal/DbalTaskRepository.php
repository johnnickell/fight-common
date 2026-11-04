<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Task\Dbal;

use Doctrine\DBAL\Connection;
use Fight\Common\Application\Mcp\Task\Exception\TaskStorageFailed;
use Fight\Common\Application\Mcp\Task\Service\CommittedTaskReader;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskDefinition;
use Fight\Common\Domain\Mcp\Task\TaskId;
use Fight\Common\Domain\Mcp\Task\TaskRepository;
use Fight\Common\Domain\Mcp\Task\TaskSelection;
use RuntimeException;
use Throwable;

/**
 * Class DbalTaskRepository
 *
 * Persists complete aggregates and supplies the narrow committed-read capability without owning transactions
 */
final readonly class DbalTaskRepository implements TaskRepository, CommittedTaskReader
{
    private Connection $connection;

    /**
     * Constructs DbalTaskRepository
     */
    public function __construct(private DbalTaskTransaction $transactionalUnitOfWork)
    {
        $this->connection = $transactionalUnitOfWork->connection();
    }

    /**
     * @inheritDoc
     */
    public function getById(string $scope, TaskId $id): ?Task
    {
        $this->transactionalUnitOfWork->requireOwnedTransaction();

        return $this->load($scope, $id);
    }

    /**
     * @inheritDoc
     */
    public function save(Task $task): void
    {
        $this->transactionalUnitOfWork->requireOwnedTransaction();
        $payload = json_encode($task->toArray(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        if (strlen($payload) > Task::MAX_STORED_BYTES) {
            throw new RuntimeException('Task exceeds the stored representation bound.');
        }

        $key = ['scope' => $task->definition()->scope(), 'task_id' => $task->definition()->id()->toString()];
        // The namespace lock makes replacement atomic across every supported backend without an upsert dialect.
        $this->connection->delete('fight_tasks', $key);
        $this->connection->insert('fight_tasks', [...$key, 'payload' => $payload]);
    }

    /**
     * @inheritDoc
     */
    public function remove(TaskSelection $selection): ?TaskSelection
    {
        $this->transactionalUnitOfWork->requireOwnedTransaction();
        $removed = [];
        foreach ($selection->ids() as $id) {
            $key = ['scope' => $selection->scope(), 'task_id' => $id->toString()];
            if ($this->connection->delete('fight_tasks', $key) === 1) {
                $removed[] = $id;
            }
        }

        return $removed === [] ? null : TaskSelection::fromIds($selection->scope(), $removed);
    }

    /**
     * @inheritDoc
     */
    public function read(string $scope, TaskId $id): ?Task
    {
        $this->transactionalUnitOfWork->requireCommittedRead();

        return $this->load($scope, $id);
    }

    /**
     * Returns one bounded coherent snapshot or an explicit storage failure
     */
    private function load(string $scope, TaskId $id): ?Task
    {
        TaskDefinition::reference($scope);
        try {
            $payload = $this->connection->fetchOne(
                'SELECT SUBSTRING(payload, 1, 40961) FROM fight_tasks WHERE scope = ? AND task_id = ?',
                [$scope, $id->toString()]
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
            if ($task->definition()->scope() !== $scope || !$task->definition()->id()->equals($id)) {
                throw new RuntimeException('Inconsistent stored Task identity.');
            }

            return $task;
        } catch (Throwable $throwable) {
            throw new TaskStorageFailed(false, $throwable);
        }
    }
}
