<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Task;

use Closure;
use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Adapter\Task\Dbal\DbalTaskStore;
use Fight\Common\Application\Task\CommandHandler\CreateTaskHandler;
use Fight\Common\Application\Task\CommandHandler\RecordTaskStateHandler;
use Fight\Common\Application\Task\QueryHandler\GetTaskHandler;
use Fight\Common\Application\Task\Service\TaskCreationAdmission;
use Fight\Common\Application\Task\Service\TaskLifecycle;
use Fight\Common\Application\Task\Service\TaskReadAdmission;
use Fight\Common\Application\Task\Service\TaskUpdateAdmission;
use Fight\Common\Application\Task\Service\ToolTaskPayloadPolicy;
use Fight\Common\Domain\Task\Command\CreateTask;
use Fight\Common\Domain\Task\Command\RecordTaskState;
use Fight\Common\Domain\Task\Query\GetTask;
use Fight\Common\Domain\Task\TaskDefinition;
use Fight\Common\Domain\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;

final class TaskFixture implements TaskCreationAdmission, TaskUpdateAdmission, TaskReadAdmission
{
    public const string ID = '0123456789abcdef0123456789abcdef';
    public bool $createAllowed = true;
    public bool $updateAllowed = true;
    public bool $readAllowed = true;
    public bool $discloseAllowed = true;
    public int $now = 1793059200000;
    public int $admissions = 0;
    public ?Closure $onAdmission = null;
    public readonly DbalTaskStore $store;
    public readonly RoutingCommandBus $commands;
    public readonly RoutingQueryBus $queries;
    public readonly SimpleEventDispatcher $events;
    public readonly TaskLifecycle $lifecycle;

    public function __construct(Connection $connection)
    {
        $this->store = new DbalTaskStore($connection);
        $this->events = new SimpleEventDispatcher();
        $this->lifecycle = new TaskLifecycle($this->store, $this, $this, $this, new ToolTaskPayloadPolicy(fn () => $this->discloseAllowed), $this->events, fn () => $this->now);
        $commands = new InMemoryCommandRouter();
        $commands->registerHandler(CreateTask::class, new CreateTaskHandler($this->lifecycle));
        $commands->registerHandler(RecordTaskState::class, new RecordTaskStateHandler($this->lifecycle));
        $this->commands = new RoutingCommandBus($commands);
        $queries = new InMemoryQueryRouter();
        $queries->registerHandler(GetTask::class, new GetTaskHandler($this->lifecycle));
        $this->queries = new RoutingQueryBus($queries);
    }

    public static function definition(string $id = self::ID, ?StrictJson $operation = null): TaskDefinition
    {
        return TaskDefinition::create($id, 'scope', 'producer', $operation ?? StrictJson::fromObject([
            'kind' => 'tool', 'name' => 'example.operation', 'revision' => 'immutable-v1',
            'output_schema' => (object) ['type' => 'object', 'properties' => (object) ['ok' => (object) ['type' => 'boolean']], 'required' => ['ok'], 'additionalProperties' => false]
        ]));
    }

    public static function completed(bool $isError = false): TaskState
    {
        return TaskState::create('completed', result: StrictJson::fromObject(['content' => [(object) ['type' => 'text', 'text' => $isError ? 'Business refusal' : 'Finished']], 'structuredContent' => (object) ['ok' => !$isError], 'isError' => $isError]));
    }

    public static function input(): TaskState
    {
        return TaskState::create('input_required', input: StrictJson::fromObject(['request-1' => (object) ['type' => 'form', 'message' => 'Provide input', 'schema' => (object) ['type' => 'object', 'properties' => (object) ['answer' => (object) ['type' => 'string']], 'required' => ['answer']]]]));
    }

    public function create(?int $ttl = null, ?int $poll = null, string $id = self::ID): void
    {
        $this->commands->execute(new CreateTask(self::definition($id), TaskState::create('working'), $ttl, $poll));
    }

    public function record(TaskState $state, int $expectedVersion = 1, ?int $poll = null): void
    {
        $this->commands->execute(new RecordTaskState('scope', self::ID, 'producer', $expectedVersion, $state, $poll));
    }

    public function get(string $id = self::ID, string $scope = 'scope'): ?StrictJson
    {
        return $this->queries->fetch(new GetTask($scope, $id));
    }

    public function allowsCreation(TaskDefinition $definition): bool
    {
        ++$this->admissions;
        ($this->onAdmission ?? static fn () => null)();
        return $this->createAllowed && $definition->scope() === 'scope' && $definition->producer() === 'producer';
    }

    public function allowsUpdate(string $scope, string $id, string $producer): bool
    {
        ++$this->admissions;
        ($this->onAdmission ?? static fn () => null)();
        return $this->updateAllowed && $scope === 'scope' && $producer === 'producer';
    }

    public function allowsRead(string $scope, string $id): bool
    {
        return $this->readAllowed && $scope === 'scope';
    }
}
