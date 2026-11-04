<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Task;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Task\Dbal\DbalTaskSchema;
use Fight\Common\Adapter\Messaging\Command\Sync\CommandPipeline;
use Fight\Common\Adapter\Messaging\Query\QueryPipeline;
use Fight\Common\Application\Mcp\Task\CommandHandler\AcquireTaskInputHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\CreateTaskHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\RecordTaskStateHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\RequestTaskInputHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\RetireTaskInputHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\SupplyTaskInputHandler;
use Fight\Common\Application\Mcp\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\Service\TaskInputForms;
use Fight\Common\Application\Mcp\Task\Service\TaskPayloadPolicy;
use Fight\Common\Application\Mcp\Task\Service\ToolTaskPayloadPolicy;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Query\QueryFilter;
use Fight\Common\Domain\Mcp\Task\Command\AcquireTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\CreateTask;
use Fight\Common\Domain\Mcp\Task\Command\RecordTaskState;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\RetireTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\SupplyTaskInput;
use Fight\Common\Domain\Mcp\Task\Query\GetTask;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskInput;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Event\AllEvents;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(CreateTaskHandler::class)]
#[CoversClass(RecordTaskStateHandler::class)]
#[CoversClass(RequestTaskInputHandler::class)]
#[CoversClass(SupplyTaskInputHandler::class)]
#[CoversClass(AcquireTaskInputHandler::class)]
#[CoversClass(RetireTaskInputHandler::class)]
final class TaskHandlerTest extends UnitTestCase
{
    #[DataProvider('payloadMutations')]
    public function test_that_each_handler_rejects_unsafe_candidate_payloads_without_writes_or_facts(string $operation): void
    {
        $connection = $this->database();
        $fixture = new TaskFixture($connection);
        $fixture->create();
        $fixture->issue();
        if ($operation !== 'supply') {
            $fixture->supply(self::answers());
        }
        $before = $connection->fetchAllAssociative('SELECT * FROM fight_tasks');
        $taskPayloadPolicy = $this->mock(TaskPayloadPolicy::class);
        $taskPayloadPolicy->shouldReceive('validate')->once()->andThrow(new RuntimeException('private payload'));
        $eventDispatcher = $this->mock(EventDispatcher::class);
        $eventDispatcher->shouldNotReceive('trigger');
        [$commandHandler, $command] = $this->mutation($operation, $fixture, $taskPayloadPolicy, $eventDispatcher);

        try {
            $commandHandler->handle(CommandMessage::create($command));
            self::fail('Unsafe state must not commit');
        } catch (TaskRejected $failure) {
            self::assertSame('Task operation rejected.', $failure->getMessage());
            self::assertNull($failure->getPrevious());
        }

        self::assertFalse($connection->isTransactionActive());
        self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM fight_tasks'));
    }

    public static function payloadMutations(): iterable
    {
        foreach (['create', 'record', 'request', 'supply', 'acquire', 'retire'] as $operation) {
            yield $operation => [$operation];
        }
    }

    #[DataProvider('factMutations')]
    public function test_that_each_handler_reports_delivery_failure_only_after_its_state_commits(string $operation): void
    {
        $connection = $this->database();
        $fixture = new TaskFixture($connection);
        $fixture->create();
        $fixture->issue();
        if ($operation !== 'supply') {
            $fixture->supply(self::answers());
        }
        $before = $fixture->committedTaskReader->read('scope', TaskFixture::ID)->version();
        $eventDispatcher = $this->mock(EventDispatcher::class);
        $eventDispatcher->shouldReceive('trigger')->once()->andReturnUsing(static function ($event) use ($connection): void {
            self::assertFalse($connection->isTransactionActive());
            self::assertSame($event->toArray()['version'], (new TaskFixture($connection))->get()->get('version'));
            throw new RuntimeException('private subscriber');
        });
        [$commandHandler, $command] = $this->mutation($operation, $fixture, new ToolTaskPayloadPolicy(static fn () => true), $eventDispatcher);

        try {
            $commandHandler->handle(CommandMessage::create($command));
            self::fail('Committed delivery failure must be distinguished');
        } catch (CommittedTaskDispatchFailed $failure) {
            self::assertSame($before + 1, $failure->event()->toArray()['version']);
        }

        self::assertSame($before + 1, $fixture->get()->get('version'));
        if ($operation === 'acquire') {
            self::assertTrue($fixture->observe()->get('groups')->get(TaskFixture::KEY_1)->get('consumed'));
            $this->expectException(TaskConflict::class);
            $fixture->acquire();
        }
    }

    public static function factMutations(): iterable
    {
        foreach (['record', 'request', 'supply', 'acquire', 'retire'] as $operation) {
            yield $operation => [$operation];
        }
    }

    public function test_that_acquisition_and_retirement_hide_throwing_independent_policies_without_consuming_data(): void
    {
        $fixture = new TaskFixture($this->database());
        $fixture->create();
        $fixture->issue();
        $fixture->supply(self::answers());
        $before = $fixture->observe()->toString();
        foreach (['onInputAdmission', 'onCancellationAdmission', 'onAdmission'] as $callback) {
            $fixture->$callback = static fn () => throw new RuntimeException('private authority');
            try {
                if ($callback === 'onAdmission') {
                    $fixture->commands->execute(new RetireTaskInput('scope', TaskFixture::ID, 'producer', 3, TaskFixture::KEY_1));
                } else {
                    $fixture->acquire();
                }
                self::fail('Throwing authority must reject');
            } catch (TaskRejected $failure) {
                self::assertNull($failure->getPrevious());
            }
            $fixture->$callback = null;
            self::assertSame($before, $fixture->observe()->toString());
        }
    }

    public function test_that_continuation_authority_does_not_imply_permission_to_observe_cancellation(): void
    {
        $fixture = new TaskFixture($this->database());
        $fixture->create();
        $fixture->issue();
        $fixture->supply(self::answers());
        $before = $fixture->observe()->toString();
        $fixture->cancelObserveAllowed = false;

        try {
            $fixture->acquire();
            self::fail('Independent intent observation permission is required');
        } catch (TaskRejected) {
            self::assertNull($fixture->observe());
        }

        $fixture->cancelObserveAllowed = true;
        self::assertSame($before, $fixture->observe()->toString());
        $fixture->acquire();
        self::assertTrue($fixture->observe()->get('groups')->get(TaskFixture::KEY_1)->get('consumed'));
    }

    #[DataProvider('unavailableInput')]
    public function test_that_acquisition_and_retirement_reject_missing_or_expired_tasks_without_facts(string $operation, bool $expired): void
    {
        $connection = $this->database();
        $fixture = new TaskFixture($connection);
        if ($expired) {
            $fixture->create(1000);
            $fixture->issue();
            $fixture->supply(self::answers());
            $fixture->now += 1000;
        }
        $before = $connection->fetchAllAssociative('SELECT * FROM fight_tasks');
        $fixture->events->addHandler(ClassName::underscore(AllEvents::class), static fn () => self::fail('No unavailable mutation facts'));
        try {
            if ($operation === 'acquire') {
                $fixture->acquire();
            } else {
                $fixture->commands->execute(new RetireTaskInput('scope', TaskFixture::ID, 'producer', 3, TaskFixture::KEY_1));
            }
            self::fail('Unavailable Tasks must reject');
        } catch (TaskRejected) {
            self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM fight_tasks'));
        }
    }

    public static function unavailableInput(): iterable
    {
        yield ['acquire', false];
        yield ['acquire', true];
        yield ['retire', false];
        yield ['retire', true];
    }

    public function test_that_exhausted_input_version_rejects_answers_without_partial_persistence(): void
    {
        $connection = $this->database();
        $fixture = new TaskFixture($connection);
        $fixture->create();
        $fixture->issue();
        $maximum = Task::fromArray(array_replace($fixture->committedTaskReader->read('scope', TaskFixture::ID)->toArray(), ['version' => Task::MAX_VERSION]));
        $fixture->transactionalUnitOfWork->commitTransactional(fn () => $fixture->taskRepository->save($maximum));
        $before = $connection->fetchAllAssociative('SELECT * FROM fight_tasks');
        try {
            $fixture->supply(self::answers());
            self::fail('Exhausted version must conflict');
        } catch (TaskConflict) {
            self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM fight_tasks'));
            self::assertSame([], $fixture->observe()->get('groups')->get(TaskFixture::KEY_1)->get('answers')->properties());
        }
    }

    public function test_that_duplicate_producer_evidence_does_not_repeat_a_committed_fact(): void
    {
        $fixture = new TaskFixture($this->database());
        $fixture->create();
        $fixture->record(TaskFixture::completed());
        $before = $fixture->get()->toString();
        $fixture->events->addHandler(ClassName::underscore(AllEvents::class), static fn () => self::fail('No duplicate fact'));

        $fixture->recordTaskStateHandler->handle(CommandMessage::create(new RecordTaskState('scope', TaskFixture::ID, 'producer', 1, TaskFixture::completed())));

        self::assertSame($before, $fixture->get()->toString());
    }

    public function test_that_bus_filters_surround_owned_handlers_and_queries_never_change_retained_rows(): void
    {
        $connection = $this->database();
        $fixture = new TaskFixture($connection);
        $commandBus = new CommandPipeline($fixture->commands);
        $queryBus = new QueryPipeline($fixture->queries);
        $commandFilter = $this->mock(CommandFilter::class);
        $commandFilter->shouldReceive('process')->times(5)->andReturnUsing(static function ($message, $next) use ($connection): void {
            self::assertFalse($connection->isTransactionActive());
            $next($message);
            self::assertFalse($connection->isTransactionActive());
        });
        $queryFilter = $this->mock(QueryFilter::class);
        $queryFilter->shouldReceive('process')->times(3)->andReturnUsing(static fn ($message, $next) => $next($message));
        $commandBus->addFilter($commandFilter);
        $queryBus->addFilter($queryFilter);
        $commandBus->execute(new CreateTask(TaskFixture::definition(), TaskState::create('working'), 1000));
        $batch = TaskInputForms::batch(McpInputRequired::fromRequests(['answer' => TaskFixture::form()]), 'caller', $fixture->now + 100);
        $commandBus->execute(new RequestTaskInput('scope', TaskFixture::ID, 'producer', 1, $batch));
        $commandBus->execute(new SupplyTaskInput('scope', TaskFixture::ID, 'caller', StrictJson::fromObject(self::answers())));
        $commandBus->execute(new AcquireTaskInput('scope', TaskFixture::ID, 'caller', TaskFixture::KEY_1));
        $commandBus->execute(new RequestTaskCancellation('scope', TaskFixture::ID, 'caller'));
        $before = $connection->fetchAllAssociative('SELECT * FROM fight_tasks');
        $fixture->now += 100;

        self::assertSame(5, $queryBus->fetch(new GetTask('scope', TaskFixture::ID))->get('version'));
        self::assertTrue($queryBus->fetch(new GetTaskInput('scope', TaskFixture::ID, 'caller'))->get('groups')->get(TaskFixture::KEY_1)->get('consumed'));
        self::assertNotNull($queryBus->fetch(new GetTaskCancellation('scope', TaskFixture::ID, 'caller'))->get('cancellation'));
        self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM fight_tasks'));
    }

    private function mutation(string $operation, TaskFixture $fixture, TaskPayloadPolicy $taskPayloadPolicy, EventDispatcher $eventDispatcher): array
    {
        $taskRepository = $fixture->taskRepository;
        $transactionalUnitOfWork = $fixture->transactionalUnitOfWork;
        $clock = fn (): int => $fixture->now;
        return match ($operation) {
            'create' => [new CreateTaskHandler($taskRepository, $transactionalUnitOfWork, $fixture, $taskPayloadPolicy, $eventDispatcher, $clock), new CreateTask(TaskFixture::definition(str_repeat('a', 32)), TaskState::create('working'))],
            'record' => [new RecordTaskStateHandler($taskRepository, $transactionalUnitOfWork, $fixture, $taskPayloadPolicy, $eventDispatcher, $clock), new RecordTaskState('scope', TaskFixture::ID, 'producer', 3, TaskFixture::completed())],
            'request' => [new RequestTaskInputHandler($taskRepository, $transactionalUnitOfWork, $fixture, $taskPayloadPolicy, $eventDispatcher, $clock), new RequestTaskInput('scope', TaskFixture::ID, 'producer', 3, TaskInputForms::batch(McpInputRequired::fromRequests(['answer' => TaskFixture::form()]), 'caller', $fixture->now + 1000))],
            'supply' => [new SupplyTaskInputHandler($taskRepository, $transactionalUnitOfWork, $fixture, $taskPayloadPolicy, $eventDispatcher, $clock), new SupplyTaskInput('scope', TaskFixture::ID, 'caller', StrictJson::fromObject(self::answers()))],
            'acquire' => [new AcquireTaskInputHandler($taskRepository, $transactionalUnitOfWork, $fixture, $fixture, $taskPayloadPolicy, $eventDispatcher, $clock), new AcquireTaskInput('scope', TaskFixture::ID, 'caller', TaskFixture::KEY_1)],
            'retire' => [new RetireTaskInputHandler($taskRepository, $transactionalUnitOfWork, $fixture, $taskPayloadPolicy, $eventDispatcher, $clock), new RetireTaskInput('scope', TaskFixture::ID, 'producer', 3, TaskFixture::KEY_1)]
        };
    }

    private static function answers(): array
    {
        return [TaskFixture::KEY_1 => ['action' => 'accept', 'content' => (object) ['answer' => 'accepted']]];
    }

    private function database(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new DbalTaskSchema())->install($connection);

        return $connection;
    }
}
