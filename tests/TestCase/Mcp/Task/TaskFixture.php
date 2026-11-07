<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp\Task;

use Closure;
use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskRepository;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTables;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTransaction;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Mcp\Task\CommandHandler\AcquireTaskInputHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\CreateTaskHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\RecordTaskStateHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\RemoveTasksHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\RequestTaskCancellationHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\RequestTaskInputHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\RetireTaskInputHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\SupplyTaskInputHandler;
use Fight\Common\Application\Mcp\Task\QueryHandler\GetTaskCancellationHandler;
use Fight\Common\Application\Mcp\Task\QueryHandler\GetTaskHandler;
use Fight\Common\Application\Mcp\Task\QueryHandler\GetTaskInputHandler;
use Fight\Common\Application\Mcp\Task\Service\CommittedTaskReader;
use Fight\Common\Application\Mcp\Task\Service\TaskCancellationAdmission;
use Fight\Common\Application\Mcp\Task\Service\TaskCreationAdmission;
use Fight\Common\Application\Mcp\Task\Service\TaskInputAdmission;
use Fight\Common\Application\Mcp\Task\Service\TaskInputForms;
use Fight\Common\Application\Mcp\Task\Service\TaskReadAdmission;
use Fight\Common\Application\Mcp\Task\Service\TaskRemovalAdmission;
use Fight\Common\Application\Mcp\Task\Service\TaskUpdateAdmission;
use Fight\Common\Application\Mcp\Task\Service\ToolTaskPayloadPolicy;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequest;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Domain\Mcp\Task\Command\AcquireTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\CreateTask;
use Fight\Common\Domain\Mcp\Task\Command\RecordTaskState;
use Fight\Common\Domain\Mcp\Task\Command\RemoveTasks;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\RetireTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\SupplyTaskInput;
use Fight\Common\Domain\Mcp\Task\Query\GetTask;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskInput;
use Fight\Common\Domain\Mcp\Task\TaskDefinition;
use Fight\Common\Domain\Mcp\Task\TaskId;
use Fight\Common\Domain\Mcp\Task\TaskSelection;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;

final class TaskFixture implements TaskCreationAdmission, TaskUpdateAdmission, TaskReadAdmission, TaskRemovalAdmission, TaskInputAdmission, TaskCancellationAdmission
{
    public const string ID = '0123456789abcdef0123456789abcdef';
    public const string KEY_NAMESPACE = '5f13508bde106a136697ee60d5d1981b';
    public const string KEY_PREFIX = 'input-'.self::KEY_NAMESPACE.'-';
    public const string KEY_1 = self::KEY_PREFIX.'1';
    public const string KEY_2 = self::KEY_PREFIX.'2';
    public const string KEY_3 = self::KEY_PREFIX.'3';
    public bool $cancelAllowed = true;
    public bool $cancelObserveAllowed = true;
    public ?Closure $onCancellationAdmission = null;
    public readonly RequestTaskCancellationHandler $requestTaskCancellationHandler;
    public readonly GetTaskCancellationHandler $getTaskCancellationHandler;
    public bool $submitAllowed = true;
    public bool $observeAllowed = true;
    public bool $continueAllowed = true;
    public ?Closure $onInputAdmission = null;
    public readonly RequestTaskInputHandler $requestTaskInputHandler;
    public readonly SupplyTaskInputHandler $supplyTaskInputHandler;
    public readonly AcquireTaskInputHandler $acquireTaskInputHandler;
    public readonly RetireTaskInputHandler $retireTaskInputHandler;
    public readonly GetTaskInputHandler $getTaskInputHandler;
    public bool $createAllowed = true;
    public bool $updateAllowed = true;
    public bool $readAllowed = true;
    public string $comparison = 'caller-visibility-v1';
    public ?Closure $onRead = null;
    public ?Closure $onComparison = null;
    public bool $removeAllowed = true;
    public array $removalChecks = [];
    public array $deniedRemovalIds = [];
    public bool $discloseAllowed = true;
    public int $now = 1793059200000;
    public int $admissions = 0;
    public ?Closure $onAdmission = null;
    public readonly DbalTaskRepository $taskRepository;
    public readonly DbalTaskTransaction $transactionalUnitOfWork;
    public readonly CommittedTaskReader $committedTaskReader;
    public readonly RoutingCommandBus $commandBus;
    public readonly RoutingQueryBus $queryBus;
    public readonly SimpleEventDispatcher $eventDispatcher;
    public readonly CreateTaskHandler $createTaskHandler;
    public readonly RecordTaskStateHandler $recordTaskStateHandler;
    public readonly RemoveTasksHandler $removeTasksHandler;
    public readonly GetTaskHandler $getTaskHandler;

    public function __construct(Connection $connection, DbalTaskTables $tables = new DbalTaskTables())
    {
        $this->transactionalUnitOfWork = new DbalTaskTransaction($connection, $tables);
        $this->taskRepository = new DbalTaskRepository($this->transactionalUnitOfWork);
        $this->committedTaskReader = $this->taskRepository;
        $this->eventDispatcher = new SimpleEventDispatcher();
        $taskPayloadPolicy = new ToolTaskPayloadPolicy(fn () => $this->discloseAllowed);
        $clock = fn (): int => $this->now;
        $this->createTaskHandler = new CreateTaskHandler($this->taskRepository, $this->transactionalUnitOfWork, $this, $taskPayloadPolicy, $this->eventDispatcher, $clock);
        $this->recordTaskStateHandler = new RecordTaskStateHandler($this->taskRepository, $this->transactionalUnitOfWork, $this, $taskPayloadPolicy, $this->eventDispatcher, $clock);
        $this->removeTasksHandler = new RemoveTasksHandler($this->taskRepository, $this->transactionalUnitOfWork, $this, $this->eventDispatcher);
        $this->getTaskHandler = new GetTaskHandler($this->committedTaskReader, $this, $taskPayloadPolicy, $clock);
        $this->requestTaskInputHandler = new RequestTaskInputHandler($this->taskRepository, $this->transactionalUnitOfWork, $this, $taskPayloadPolicy, $this->eventDispatcher, $clock);
        $this->supplyTaskInputHandler = new SupplyTaskInputHandler($this->taskRepository, $this->transactionalUnitOfWork, $this, $taskPayloadPolicy, $this->eventDispatcher, $clock);
        $this->acquireTaskInputHandler = new AcquireTaskInputHandler($this->taskRepository, $this->transactionalUnitOfWork, $this, $this, $taskPayloadPolicy, $this->eventDispatcher, $clock);
        $this->retireTaskInputHandler = new RetireTaskInputHandler($this->taskRepository, $this->transactionalUnitOfWork, $this, $taskPayloadPolicy, $this->eventDispatcher, $clock);
        $this->getTaskInputHandler = new GetTaskInputHandler($this->committedTaskReader, $this, $this, $clock);
        $this->requestTaskCancellationHandler = new RequestTaskCancellationHandler($this->taskRepository, $this->transactionalUnitOfWork, $this->committedTaskReader, $this, $this->eventDispatcher, $clock);
        $this->getTaskCancellationHandler = new GetTaskCancellationHandler($this->committedTaskReader, $this, $clock);
        $commandRouter = new InMemoryCommandRouter();
        $commandRouter->registerHandler(RequestTaskCancellation::class, $this->requestTaskCancellationHandler);
        $commandRouter->registerHandler(RequestTaskInput::class, $this->requestTaskInputHandler);
        $commandRouter->registerHandler(SupplyTaskInput::class, $this->supplyTaskInputHandler);
        $commandRouter->registerHandler(AcquireTaskInput::class, $this->acquireTaskInputHandler);
        $commandRouter->registerHandler(RetireTaskInput::class, $this->retireTaskInputHandler);
        $commandRouter->registerHandler(CreateTask::class, $this->createTaskHandler);
        $commandRouter->registerHandler(RecordTaskState::class, $this->recordTaskStateHandler);
        $commandRouter->registerHandler(RemoveTasks::class, $this->removeTasksHandler);
        $this->commandBus = new RoutingCommandBus($commandRouter);
        $queryRouter = new InMemoryQueryRouter();
        $queryRouter->registerHandler(GetTaskCancellation::class, $this->getTaskCancellationHandler);
        $queryRouter->registerHandler(GetTaskInput::class, $this->getTaskInputHandler);
        $queryRouter->registerHandler(GetTask::class, $this->getTaskHandler);
        $this->queryBus = new RoutingQueryBus($queryRouter);
    }

    public static function id(string $value = self::ID): TaskId
    {
        return TaskId::fromString($value);
    }

    public static function definition(string $id = self::ID, ?StrictJson $operation = null): TaskDefinition
    {
        return TaskDefinition::create(self::id($id), 'scope', 'producer', $operation ?? StrictJson::fromObject([
            'kind' => 'tool', 'name' => 'example.operation', 'revision' => 'immutable-v1', 'arguments' => (object) [],
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
        $this->commandBus->execute(new CreateTask(self::definition($id), TaskState::create('working'), $ttl, $poll));
    }

    public function record(TaskState $state, int $expectedVersion = 1, ?int $poll = null): void
    {
        if ($state->status() === 'input_required') {
            $this->issue(expectedVersion: $expectedVersion);
            return;
        }
        $this->commandBus->execute(new RecordTaskState('scope', self::id(), 'producer', $expectedVersion, $state, $poll));
    }

    public static function form(string $message = 'Provide input', array $rules = []): McpInputRequest
    {
        return McpInputRequest::form($message, ['type' => 'object', 'properties' => (object) ['answer' => (object) ['type' => 'string']], 'required' => ['answer']], $rules);
    }

    public function issue(bool $confirmation = false, int $count = 1, int $expectedVersion = 1, string $caller = 'caller', ?int $expiresAt = null, string $id = self::ID): void
    {
        $forms = [];
        for ($index = 0; $index < $count; ++$index) { $forms['form-'.$index] = self::form(); }
        $input = $confirmation ? McpInputRequired::confirmation($forms) : McpInputRequired::fromRequests($forms);
        $this->commandBus->execute(new RequestTaskInput('scope', self::id($id), 'producer', $expectedVersion, TaskInputForms::batch($input, $caller, $expiresAt ?? $this->now + 1000)));
    }

    public function supply(array $responses, string $caller = 'caller', string $scope = 'scope', string $id = self::ID): void
    {
        $this->commandBus->execute(new SupplyTaskInput($scope, self::id($id), $caller, StrictJson::fromObject($responses)));
    }

    public function observe(string $caller = 'caller', string $scope = 'scope', string $id = self::ID): ?StrictJson
    {
        return $this->queryBus->fetch(new GetTaskInput($scope, self::id($id), $caller));
    }

    public function acquire(string $group = self::KEY_1, string $caller = 'caller'): void
    {
        $this->commandBus->execute(new AcquireTaskInput('scope', self::id(), $caller, $group));
    }

    public function cancel(string $scope = 'scope', string $id = self::ID, string $caller = 'caller'): void
    {
        $this->commandBus->execute(new RequestTaskCancellation($scope, self::id($id), $caller));
    }

    public function cancellation(string $scope = 'scope', string $id = self::ID, string $caller = 'caller'): ?StrictJson
    {
        return $this->queryBus->fetch(new GetTaskCancellation($scope, self::id($id), $caller));
    }

    public function allowsCancellation(string $scope, TaskId $id, string $caller): bool
    {
        ($this->onCancellationAdmission ?? static fn () => null)();
        return $this->cancelAllowed && $scope === 'scope';
    }

    public function allowsCancellationObservation(string $scope, TaskId $id, string $caller): bool
    {
        ($this->onCancellationAdmission ?? static fn () => null)();
        return $this->cancelObserveAllowed && $scope === 'scope';
    }

    public function allowsSubmission(string $scope, TaskId $id, string $caller): bool
    {
        ($this->onInputAdmission ?? static fn () => null)();
        return $this->submitAllowed && $scope === 'scope';
    }

    public function allowsObservation(string $scope, TaskId $id, string $caller): bool
    {
        ($this->onInputAdmission ?? static fn () => null)();
        return $this->observeAllowed && $scope === 'scope';
    }

    public function allowsContinuation(string $scope, TaskId $id, string $caller, string $group): bool
    {
        ($this->onInputAdmission ?? static fn () => null)();
        return $this->continueAllowed && $scope === 'scope';
    }

    public function remove(array $ids = [self::ID], string $scope = 'scope'): void
    {
        $this->commandBus->execute(new RemoveTasks(TaskSelection::fromIds($scope, array_map(self::id(...), $ids))));
    }

    public function allowsRemoval(string $scope, TaskId $id): bool
    {
        ++$this->admissions;
        $this->removalChecks[] = [$scope, $id->toString()];
        ($this->onAdmission ?? static fn () => null)();
        return $this->removeAllowed && $scope === 'scope' && !in_array($id->toString(), $this->deniedRemovalIds, true);
    }

    public function get(string $id = self::ID, string $scope = 'scope'): ?StrictJson
    {
        return $this->queryBus->fetch(new GetTask($scope, self::id($id)));
    }

    public function allowsCreation(TaskDefinition $definition): bool
    {
        ++$this->admissions;
        ($this->onAdmission ?? static fn () => null)();
        return $this->createAllowed && $definition->scope() === 'scope' && $definition->producer() === 'producer';
    }

    public function allowsUpdate(string $scope, TaskId $id, string $producer): bool
    {
        ++$this->admissions;
        ($this->onAdmission ?? static fn () => null)();
        return $this->updateAllowed && $scope === 'scope' && $producer === 'producer';
    }

    public function comparisonScope(string $scope, TaskId $id): string { ($this->onComparison ?? static fn () => null)(); return $this->comparison; }

    public function allowsRead(string $scope, TaskId $id): bool
    {
        ($this->onRead ?? static fn () => null)();
        return $this->readAllowed && $scope === 'scope';
    }
}
