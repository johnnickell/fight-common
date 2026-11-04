<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Task;

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Task\Dbal\DbalTaskSchema;
use Fight\Common\Application\Mcp\Task\CommandHandler\RequestTaskCancellationHandler;
use Fight\Common\Application\Mcp\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\Exception\TaskStorageFailed;
use Fight\Common\Application\Mcp\Task\QueryHandler\GetTaskCancellationHandler;
use Fight\Common\Application\Mcp\Task\Service\CommittedTaskReader;
use Fight\Common\Application\Mcp\Task\Service\TaskCancellationLifecycle;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Command\CreateTask;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\Query\GetTask;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskRepository;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Throwable;

#[CoversClass(Task::class)]
#[CoversClass(TaskCancellationLifecycle::class)]
#[CoversClass(RequestTaskCancellation::class)]
#[CoversClass(GetTaskCancellation::class)]
#[CoversClass(RequestTaskCancellationHandler::class)]
#[CoversClass(GetTaskCancellationHandler::class)]
final class TaskCancellationTest extends UnitTestCase
{
    public function test_that_intent_is_bounded_original_evidence_not_state_or_a_consumed_stop_receipt(): void
    {
        $fixture = $this->fixture();
        $fixture->create(1000, 15);
        $fixture->issue(confirmation: true);
        $before = $fixture->committedTaskReader->read('scope', TaskFixture::ID);
        $events = [];
        $fixture->events->addHandler(
            ClassName::underscore(TaskChanged::class),
            static function ($message) use ($fixture, &$events): void {
                self::assertNotNull($fixture->committedTaskReader->read('scope', TaskFixture::ID)->cancellation());
                $events[] = $message->payload()->toArray();
            },
        );
        self::assertNull($fixture->cancellation()->get('cancellation'));

        $fixture->now += 1;
        $fixture->cancel();

        $intent = $fixture->cancellation()->get('cancellation');
        self::assertSame(['requested_at' => $fixture->now, 'version' => 3], $intent->properties());
        self::assertSame($before->state()->data()->toString(), $fixture->get()->get('state')->toString());
        self::assertArrayNotHasKey('cancellation', $fixture->get()->properties());
        self::assertSame(1000, $fixture->get()->get('ttl'));
        self::assertSame(15, $fixture->get()->get('poll_interval'));

        $fixture->now += 5;
        $fixture->cancel();

        self::assertSame(3, $fixture->get()->get('version'));
        self::assertCount(1, $events);
        self::assertSame($intent->toString(), $fixture->cancellation()->get('cancellation')->toString());

        $fixture->supply([
            TaskFixture::KEY_1 => ['action' => 'accept', 'content' => (object) ['answer' => 'accepted']],
        ]);
        $fixture->acquire();

        self::assertTrue($fixture->observe()->get('groups')->get(TaskFixture::KEY_1)->get('consumed'));
        self::assertSame($intent->toString(), $fixture->observe()->get('cancellation')->toString());

        $fixture->record(TaskFixture::completed(), 5);

        self::assertSame($intent->toString(), $fixture->cancellation()->get('cancellation')->toString());
        $terminal = $fixture->get()->toString();

        $fixture->cancel();

        self::assertSame($terminal, $fixture->get()->toString());
        self::assertSame('completed', $fixture->get()->get('state')->get('status'));

        $fixture->now = $before->view()['created_at'] + 1000;

        self::assertNull($fixture->cancellation());
        $this->reject(fn () => $fixture->cancel(), TaskRejected::class);
    }

    public function test_that_each_terminal_outcome_is_an_authorized_noop_without_new_intent_or_payload_change(): void
    {
        foreach ([
            TaskFixture::completed(),
            TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])),
            TaskState::create('cancelled'),
        ] as $state) {
            $fixture = $this->fixture();
            $fixture->create();
            $fixture->record($state);
            $before = $fixture->committedTaskReader->read('scope', TaskFixture::ID)->toArray();
            $fixture->events->addHandler(
                ClassName::underscore(TaskChanged::class),
                static fn () => self::fail('No new cancellation fact'),
            );

            $fixture->cancel();

            self::assertEquals($before, $fixture->committedTaskReader->read('scope', TaskFixture::ID)->toArray());
            self::assertNull($fixture->cancellation()->get('cancellation'));

            $fixture->cancelAllowed = false;

            $this->reject(fn () => $fixture->cancel(), TaskRejected::class);
        }
    }

    public function test_that_independent_current_permissions_and_safe_absence_precede_protected_lookup_and_continuation(): void
    {
        $fixture = $this->fixture();
        self::assertNull($fixture->cancellation());
        $this->reject(fn () => $fixture->cancel(), TaskRejected::class);

        $fixture->create();
        $fixture->issue(confirmation: true);
        $fixture->supply([
            TaskFixture::KEY_1 => ['action' => 'accept', 'content' => (object) ['answer' => 'yes']],
        ]);
        $fixture->cancelAllowed = false;

        $this->reject(fn () => $fixture->cancel(), TaskRejected::class);
        self::assertNull($fixture->cancellation()->get('cancellation'));

        $fixture->cancelAllowed = true;
        $fixture->readAllowed = false;
        $fixture->updateAllowed = false;
        $fixture->cancelObserveAllowed = false;

        $fixture->cancel();

        self::assertNull($fixture->get());
        self::assertNull($fixture->cancellation());
        self::assertNull($fixture->observe());
        $this->reject(fn () => $fixture->acquire(), TaskRejected::class);

        $fixture->cancelObserveAllowed = true;
        self::assertNotNull($fixture->observe()->get('cancellation'));

        $fixture->continueAllowed = false;
        $this->reject(fn () => $fixture->acquire(), TaskRejected::class);

        $fixture->cancelAllowed = false;
        $this->reject(fn () => $fixture->cancel(), TaskRejected::class);

        $fixture->cancelAllowed = true;
        self::assertNull($fixture->cancellation(scope: 'other'));
        $this->reject(fn () => $fixture->cancel(scope: 'other'), TaskRejected::class);
        self::assertNull($fixture->cancellation(id: str_repeat('f', 32)));
        $this->reject(fn () => $fixture->cancel(id: str_repeat('f', 32)), TaskRejected::class);

        $fixture->onCancellationAdmission = static fn () => throw new RuntimeException('private policy');

        self::assertNull($fixture->cancellation());
        self::assertNull($fixture->observe());
        $this->reject(fn () => $fixture->cancel(), TaskRejected::class);

        $fixture->onCancellationAdmission = null;
        $fixture->remove();

        self::assertNull($fixture->cancellation());
        $this->reject(fn () => $fixture->cancel(), TaskRejected::class);
    }

    public function test_that_domain_fences_preserve_intent_and_reject_corrupt_or_exhausted_admission_evidence(): void
    {
        $task = Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 10);

        $intent = $task->requestCancellation(1);

        self::assertSame(10, $intent->cancellation()->get('requested_at'));
        self::assertNull($intent->requestCancellation(12));

        $updated = $intent->transition('producer', 2, TaskFixture::completed(), null, 20);

        self::assertSame(
            $intent->cancellation()->toString(),
            Task::fromArray($updated->toArray())->cancellation()->toString(),
        );
        $maximum = Task::fromArray(array_replace($task->toArray(), [
            'version' => Task::MAX_VERSION,
            'last_digest' => str_repeat('a', 64),
        ]));
        $this->reject(fn () => $maximum->requestCancellation(10), DomainException::class);

        foreach ([
            ['cancellation_requested_at' => 9, 'cancellation_version' => 2],
            ['cancellation_requested_at' => 11, 'cancellation_version' => 2],
            ['cancellation_requested_at' => 10, 'cancellation_version' => 1],
            ['cancellation_requested_at' => 10, 'cancellation_version' => 3],
            ['cancellation_version' => null],
            ['cancellation_requested_at' => null],
        ] as $patch) {
            $this->reject(fn () => Task::fromArray(array_replace($intent->toArray(), $patch)), DomainException::class);
        }

        $fixture = $this->fixture();
        $fixture->create();
        $fixture->transactionalUnitOfWork->commitTransactional(fn () => $fixture->taskRepository->save($maximum));

        $this->reject(fn () => $fixture->cancel(), TaskConflict::class);
    }

    public function test_that_postcommit_failure_and_unavailable_reconstruction_never_claim_rollback_or_stopping(): void
    {
        $fixture = $this->fixture();
        $fixture->create();
        $calls = 0;
        $fixture->events->addHandler(
            ClassName::underscore(TaskChanged::class),
            static function () use (&$calls): void {
                ++$calls;
                throw new RuntimeException('private delivery');
            },
        );

        $this->reject(fn () => $fixture->cancel(), CommittedTaskDispatchFailed::class);

        self::assertNotNull($fixture->cancellation()->get('cancellation'));

        $fixture->cancel();

        self::assertSame(1, $calls);
        $initial = Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, $fixture->now);
        foreach ([
            null,
            $initial,
            new TaskStorageFailed(false, new RuntimeException('Read failed after commit')),
        ] as $reconstructed) {
            $taskRepository = $this->mock(TaskRepository::class);
            $transactionalUnitOfWork = $this->mock(TransactionalUnitOfWork::class);
            $committedTaskReader = $this->mock(CommittedTaskReader::class);
            $transactionalUnitOfWork->shouldReceive('commitTransactional')->once()->andReturnUsing(static fn ($operation) => $operation());
            $taskRepository->shouldReceive('getById')->once()->with('scope', TaskFixture::ID)->andReturn($initial);
            $taskRepository->shouldReceive('save')->once();
            if ($reconstructed instanceof Throwable) {
                $committedTaskReader->shouldReceive('read')->once()->andThrow($reconstructed);
            } else {
                $committedTaskReader->shouldReceive('read')->once()->andReturn($reconstructed);
            }
            $service = new TaskCancellationLifecycle($taskRepository, $transactionalUnitOfWork, $committedTaskReader, $fixture, $fixture->events, fn () => $fixture->now);

            try {
                $service->request(new RequestTaskCancellation('scope', TaskFixture::ID, 'caller'));
                self::fail('No acknowledgment without reconstruction');
            } catch (TaskStorageFailed $failure) {
                self::assertTrue($failure->outcomeUncertain());
            }
        }
    }

    public function test_that_command_query_envelopes_and_handlers_keep_existing_bus_contracts(): void
    {
        $fixture = $this->fixture();
        foreach ([
            new RequestTaskCancellation('scope', TaskFixture::ID, 'caller'),
            new GetTaskCancellation('scope', TaskFixture::ID, 'caller'),
        ] as $message) {
            self::assertSame($message->toArray(), $message::fromArray($message->toArray())->toArray());
        }
        self::assertSame(RequestTaskCancellation::class, RequestTaskCancellationHandler::commandRegistration());
        self::assertSame(GetTaskCancellation::class, GetTaskCancellationHandler::queryRegistration());
        $this->reject(
            fn () => (new RequestTaskCancellationHandler($fixture->cancellationLifecycle))
                ->handle(CommandMessage::create(new CreateTask(TaskFixture::definition(), TaskState::create('working')))),
            InvalidArgumentException::class,
        );
        $this->reject(
            fn () => (new GetTaskCancellationHandler($fixture->cancellationLifecycle))
                ->handle(QueryMessage::create(new GetTask('scope', TaskFixture::ID))),
            InvalidArgumentException::class,
        );
    }

    private function fixture(): TaskFixture
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new DbalTaskSchema())->install($connection);

        return new TaskFixture($connection);
    }

    private function reject(callable $operation, string $type): void
    {
        try {
            $operation();
            self::fail('Expected '.$type);
        } catch (Throwable $failure) {
            self::assertInstanceOf($type, $failure);
        }
    }
}
