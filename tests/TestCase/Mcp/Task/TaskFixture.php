<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp\Task;

use Closure;
use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\Mcp\Task\Dbal\DbalTaskStore;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Mcp\Task\CommandHandler\AcquireTaskInputHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\CreateTaskHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\RecordTaskStateHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\RemoveTasksHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\RequestTaskInputHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\RetireTaskInputHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\SupplyTaskInputHandler;
use Fight\Common\Application\Mcp\Task\QueryHandler\GetTaskHandler;
use Fight\Common\Application\Mcp\Task\QueryHandler\GetTaskInputHandler;
use Fight\Common\Application\Mcp\Task\Service\TaskCreationAdmission;
use Fight\Common\Application\Mcp\Task\Service\TaskInputAdmission;
use Fight\Common\Application\Mcp\Task\Service\TaskInputForms;
use Fight\Common\Application\Mcp\Task\Service\TaskInputLifecycle;
use Fight\Common\Application\Mcp\Task\Service\TaskLifecycle;
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
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\RetireTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\SupplyTaskInput;
use Fight\Common\Domain\Mcp\Task\Query\GetTask;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskInput;
use Fight\Common\Domain\Mcp\Task\TaskDefinition;
use Fight\Common\Domain\Mcp\Task\TaskSelection;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;

final class TaskFixture implements TaskCreationAdmission, TaskUpdateAdmission, TaskReadAdmission, TaskRemovalAdmission, TaskInputAdmission
{
    public const string ID = '0123456789abcdef0123456789abcdef';
    public const string KEY_NAMESPACE = '5f13508bde106a136697ee60d5d1981b';
    public const string KEY_PREFIX = 'input-'.self::KEY_NAMESPACE.'-';
    public const string KEY_1 = self::KEY_PREFIX.'1';
    public const string KEY_2 = self::KEY_PREFIX.'2';
    public const string KEY_3 = self::KEY_PREFIX.'3';
    public bool $submitAllowed = true;
    public bool $observeAllowed = true;
    public bool $continueAllowed = true;
    public ?Closure $onInputAdmission = null;
    public readonly TaskInputLifecycle $inputLifecycle;
    public bool $createAllowed = true;
    public bool $updateAllowed = true;
    public bool $readAllowed = true;
    public bool $removeAllowed = true;
    public array $removalChecks = [];
    public array $deniedRemovalIds = [];
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
        $this->lifecycle = new TaskLifecycle($this->store, $this, $this, $this, new ToolTaskPayloadPolicy(fn () => $this->discloseAllowed), $this->events, fn () => $this->now, $this);
        $this->inputLifecycle = new TaskInputLifecycle($this->store, $this, $this, new ToolTaskPayloadPolicy(fn () => $this->discloseAllowed), $this->events, fn () => $this->now);
        $commands = new InMemoryCommandRouter();
        $commands->registerHandler(RequestTaskInput::class, new RequestTaskInputHandler($this->inputLifecycle));
        $commands->registerHandler(SupplyTaskInput::class, new SupplyTaskInputHandler($this->inputLifecycle));
        $commands->registerHandler(AcquireTaskInput::class, new AcquireTaskInputHandler($this->inputLifecycle));
        $commands->registerHandler(RetireTaskInput::class, new RetireTaskInputHandler($this->inputLifecycle));
        $commands->registerHandler(CreateTask::class, new CreateTaskHandler($this->lifecycle));
        $commands->registerHandler(RecordTaskState::class, new RecordTaskStateHandler($this->lifecycle));
        $commands->registerHandler(RemoveTasks::class, new RemoveTasksHandler($this->lifecycle));
        $this->commands = new RoutingCommandBus($commands);
        $queries = new InMemoryQueryRouter();
        $queries->registerHandler(GetTaskInput::class, new GetTaskInputHandler($this->inputLifecycle));
        $queries->registerHandler(GetTask::class, new GetTaskHandler($this->lifecycle));
        $this->queries = new RoutingQueryBus($queries);
    }

    public static function definition(string $id = self::ID, ?StrictJson $operation = null): TaskDefinition
    {
        return TaskDefinition::create($id, 'scope', 'producer', $operation ?? StrictJson::fromObject([
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
        $this->commands->execute(new CreateTask(self::definition($id), TaskState::create('working'), $ttl, $poll));
    }

    public function record(TaskState $state, int $expectedVersion = 1, ?int $poll = null): void
    {
        if ($state->status() === 'input_required') {
            $this->issue(expectedVersion: $expectedVersion);
            return;
        }
        $this->commands->execute(new RecordTaskState('scope', self::ID, 'producer', $expectedVersion, $state, $poll));
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
        $this->commands->execute(new RequestTaskInput('scope', $id, 'producer', $expectedVersion, TaskInputForms::batch($input, $caller, $expiresAt ?? $this->now + 1000)));
    }

    public function supply(array $responses, string $caller = 'caller', string $scope = 'scope', string $id = self::ID): void
    {
        $this->commands->execute(new SupplyTaskInput($scope, $id, $caller, StrictJson::fromObject($responses)));
    }

    public function observe(string $caller = 'caller', string $scope = 'scope', string $id = self::ID): ?StrictJson
    {
        return $this->queries->fetch(new GetTaskInput($scope, $id, $caller));
    }

    public function acquire(string $group = self::KEY_1, string $caller = 'caller'): void
    {
        $this->commands->execute(new AcquireTaskInput('scope', self::ID, $caller, $group));
    }

    public function allowsSubmission(string $scope, string $id, string $caller): bool
    {
        ($this->onInputAdmission ?? static fn () => null)();
        return $this->submitAllowed && $scope === 'scope';
    }

    public function allowsObservation(string $scope, string $id, string $caller): bool
    {
        ($this->onInputAdmission ?? static fn () => null)();
        return $this->observeAllowed && $scope === 'scope';
    }

    public function allowsContinuation(string $scope, string $id, string $caller, string $group): bool
    {
        ($this->onInputAdmission ?? static fn () => null)();
        return $this->continueAllowed && $scope === 'scope';
    }

    public function remove(array $ids = [self::ID], string $scope = 'scope'): void
    {
        $this->commands->execute(new RemoveTasks(TaskSelection::fromIds($scope, $ids)));
    }

    public function allowsRemoval(string $scope, string $id): bool
    {
        ++$this->admissions;
        $this->removalChecks[] = [$scope, $id];
        ($this->onAdmission ?? static fn () => null)();
        return $this->removeAllowed && $scope === 'scope' && !in_array($id, $this->deniedRemovalIds, true);
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
