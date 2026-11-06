<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Task;

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskSchema;
use Fight\Common\Application\Mcp\Task\CommandHandler\AcquireTaskInputHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\RequestTaskInputHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\RetireTaskInputHandler;
use Fight\Common\Application\Mcp\Task\CommandHandler\SupplyTaskInputHandler;
use Fight\Common\Application\Mcp\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\QueryHandler\GetTaskInputHandler;
use Fight\Common\Application\Mcp\Task\Service\TaskInputForms;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Command\AcquireTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\CreateTask;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\RetireTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\SupplyTaskInput;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\Query\GetTask;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskInput;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskInputBatch;
use Fight\Common\Domain\Mcp\Task\TaskInputs;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Throwable;

#[CoversClass(Task::class)]
#[CoversClass(TaskInputs::class)]
#[CoversClass(TaskInputBatch::class)]
#[CoversClass(TaskInputForms::class)]
#[CoversClass(RequestTaskInput::class)]
#[CoversClass(SupplyTaskInput::class)]
#[CoversClass(AcquireTaskInput::class)]
#[CoversClass(RetireTaskInput::class)]
#[CoversClass(GetTaskInput::class)]
#[CoversClass(RequestTaskInputHandler::class)]
#[CoversClass(SupplyTaskInputHandler::class)]
#[CoversClass(AcquireTaskInputHandler::class)]
#[CoversClass(RetireTaskInputHandler::class)]
#[CoversClass(GetTaskInputHandler::class)]
final class TaskInputTest extends UnitTestCase
{
    public function test_that_partial_batches_ignore_inactive_values_and_preserve_durable_answers_and_single_use(): void
    {
        $fixture = $this->fixture(); $fixture->create(); $fixture->issue(count: 2);
        $initial = $fixture->get();
        self::assertCount(2, $initial->get('state')->get('input')->properties());
        self::assertStringNotContainsString('rules', $initial->toString());
        $fixture->supply([]); $fixture->supply(['unknown' => ['completely' => 'ignored']]);
        self::assertSame($initial->toString(), $fixture->get()->toString());
        $fixture->supply([TaskFixture::KEY_1 => self::accept('first'), 'unknown' => false]);
        self::assertSame([TaskFixture::KEY_2], array_keys($fixture->get()->get('state')->get('input')->properties()));
        $this->reject(fn () => $fixture->acquire(), TaskConflict::class);
        $fixture->supply([TaskFixture::KEY_1 => self::accept('replacement'), TaskFixture::KEY_2 => ['action' => 'decline']]);
        self::assertSame('working', $fixture->get()->get('state')->get('status'));
        $group = $fixture->observe()->get('groups')->get(TaskFixture::KEY_1);
        self::assertSame('first', $group->get('answers')->get(TaskFixture::KEY_1)->get('content')->get('answer'));
        self::assertSame('decline', $group->get('answers')->get(TaskFixture::KEY_2)->get('action'));
        self::assertTrue($group->get('ready')); self::assertFalse($group->get('consumed'));
        self::assertStringNotContainsString('rules', $fixture->observe()->toString());
        $fixture->acquire(); self::assertTrue($fixture->observe()->get('groups')->get(TaskFixture::KEY_1)->get('consumed'));
        $this->reject(fn () => $fixture->acquire(), TaskConflict::class);
        $fixture->commandBus->execute(new RetireTaskInput('scope', TaskFixture::id(), 'producer', $fixture->get()->get('version'), TaskFixture::KEY_1));
        $fixture->issue(expectedVersion: $fixture->get()->get('version'));
        self::assertSame([TaskFixture::KEY_3], array_keys($fixture->get()->get('state')->get('input')->properties()));
        $fixture->supply([TaskFixture::KEY_1 => self::accept('old')]);
        self::assertSame([TaskFixture::KEY_3], array_keys($fixture->get()->get('state')->get('input')->properties()));
        $this->reject(fn () => $fixture->issue(expectedVersion: 1), TaskConflict::class);
        $this->reject(fn () => $fixture->commandBus->execute(new RetireTaskInput('scope', TaskFixture::id(), 'producer', 1, TaskFixture::KEY_3)), TaskConflict::class);
        $this->reject(fn () => $fixture->commandBus->execute(new RetireTaskInput('scope', TaskFixture::id(), 'producer', $fixture->get()->get('version'), 'missing')), TaskConflict::class);
    }

    public function test_that_all_active_validation_precedes_atomic_admission_and_private_rule_errors_are_concealed(): void
    {
        $fixture = $this->fixture(); $fixture->create();
        $form = TaskFixture::form(rules: [['field' => 'answer', 'label' => 'private-rule-label', 'rules' => 'not_blank']]);
        $batch = TaskInputForms::batch(McpInputRequired::fromRequests(['a' => $form, 'b' => $form]), 'caller', $fixture->now + 1000);
        $fixture->commandBus->execute(new RequestTaskInput('scope', TaskFixture::id(), 'producer', 1, $batch));
        $before = $fixture->get()->toString();
        foreach ([['action' => 'accept', 'content' => (object) ['answer' => 1]], ['action' => 'accept', 'content' => (object) ['answer' => '   ']], ['action' => 'decline', 'content' => (object) []], ['action' => 'maybe'], false] as $bad) {
            $this->reject(fn () => $fixture->supply([TaskFixture::KEY_1 => self::accept('valid'), TaskFixture::KEY_2 => $bad]), TaskRejected::class);
            self::assertSame($before, $fixture->get()->toString());
            self::assertSame([], $fixture->observe()->get('groups')->get(TaskFixture::KEY_1)->get('answers')->properties());
        }
        $fixture->supply([TaskFixture::KEY_1 => ['action' => 'cancel'], TaskFixture::KEY_2 => self::accept('valid')]);
        self::assertSame('cancel', $fixture->observe()->get('groups')->get(TaskFixture::KEY_1)->get('answers')->get(TaskFixture::KEY_1)->get('action'));
        $fixture->acquire();
        $tampered = $batch->data()->get('requests')->get('a')->with('public', StrictJson::fromObject(['type' => 'form']));
        $wrong = TaskInputBatch::create('caller', $fixture->now + 1000, false, StrictJson::fromObject(['a' => $tampered]));
        $this->reject(fn () => $fixture->commandBus->execute(new RequestTaskInput('scope', TaskFixture::id(), 'producer', $fixture->get()->get('version'), $wrong)), TaskRejected::class);
    }

    #[DataProvider('refusals')]
    public function test_that_any_confirmation_refusal_retires_only_its_group_and_never_grants_continuation(string $action): void
    {
        $fixture = $this->fixture(); $fixture->create(); $fixture->issue(confirmation: true, count: 2);
        $fixture->issue(expectedVersion: 2);
        $fixture->supply([TaskFixture::KEY_1 => self::accept('yes')]);
        $this->reject(fn () => $fixture->acquire(), TaskConflict::class);
        $fixture->supply([TaskFixture::KEY_2 => ['action' => $action]]);
        $group = $fixture->observe()->get('groups')->get(TaskFixture::KEY_1);
        self::assertTrue($group->get('refused')); self::assertFalse($group->get('consumed'));
        $this->reject(fn () => $fixture->acquire(), TaskConflict::class);
        self::assertSame([TaskFixture::KEY_3], array_keys($fixture->get()->get('state')->get('input')->properties()));
        $fixture->supply([TaskFixture::KEY_2 => self::accept('retry')]);
        self::assertSame($action, $fixture->observe()->get('groups')->get(TaskFixture::KEY_1)->get('answers')->get(TaskFixture::KEY_2)->get('action'));
        self::assertSame('input_required', $fixture->get()->get('state')->get('status'));
    }

    public static function refusals(): iterable { yield ['decline']; yield ['cancel']; }

    public function test_that_confirmation_full_acceptance_expiry_and_postcommit_failure_never_restore_admission(): void
    {
        $fixture = $this->fixture(); $fixture->create(); $fixture->issue(confirmation: true, count: 2);
        $fixture->supply([TaskFixture::KEY_1 => self::accept('yes'), TaskFixture::KEY_2 => self::accept('yes')]);
        $events = 0;
        $fixture->eventDispatcher->addHandler(ClassName::underscore(TaskChanged::class), static function () use (&$events): void { ++$events; throw new RuntimeException('private delivery'); });
        $this->reject(fn () => $fixture->acquire(), CommittedTaskDispatchFailed::class);
        self::assertTrue($fixture->observe()->get('groups')->get(TaskFixture::KEY_1)->get('consumed'));
        $this->reject(fn () => $fixture->acquire(), TaskConflict::class); self::assertSame(1, $events);
        $fixture = $this->fixture(); $fixture->create(); $fixture->issue(confirmation: true);
        $fixture->now += 1000;
        self::assertSame('working', $fixture->get()->get('state')->get('status'));
        $fixture->supply([TaskFixture::KEY_1 => self::accept('late')]);
        self::assertSame([], $fixture->observe()->get('groups')->get(TaskFixture::KEY_1)->get('answers')->properties());
        self::assertTrue($fixture->observe()->get('groups')->get(TaskFixture::KEY_1)->get('expired'));
        $this->reject(fn () => $fixture->acquire(), TaskConflict::class);
        $this->reject(fn () => $fixture->issue(expectedVersion: 2, expiresAt: $fixture->now), TaskConflict::class);
    }

    public function test_that_permissions_binding_terminal_state_and_unavailability_do_not_confer_mutation_grants(): void
    {
        $fixture = $this->fixture();
        $this->reject(fn () => $fixture->supply([]), TaskRejected::class);
        $this->reject(fn () => $fixture->issue(), TaskRejected::class);
        self::assertNull($fixture->observe()); $fixture->create(10000); $fixture->issue();
        $before = $fixture->get()->toString();
        $fixture->supply([TaskFixture::KEY_1 => self::accept('foreign')], caller: 'other');
        self::assertSame([], $fixture->observe('other')->get('groups')->properties());
        self::assertSame($before, $fixture->get()->toString());
        $this->reject(fn () => $fixture->acquire(caller: 'other'), TaskConflict::class);
        $this->reject(fn () => $fixture->supply([], scope: 'other'), TaskRejected::class);
        $this->reject(fn () => $fixture->supply([], id: str_repeat('f', 32)), TaskRejected::class);
        $fixture->submitAllowed = false;
        $this->reject(fn () => $fixture->supply([TaskFixture::KEY_1 => self::accept('denied')]), TaskRejected::class);
        $fixture->observeAllowed = false; self::assertNull($fixture->observe());
        $fixture->observeAllowed = true; $fixture->continueAllowed = false;
        $this->reject(fn () => $fixture->acquire(), TaskRejected::class);
        $fixture->updateAllowed = false; $this->reject(fn () => $fixture->issue(expectedVersion: 2), TaskRejected::class);
        $this->reject(fn () => $fixture->commandBus->execute(new RetireTaskInput('scope', TaskFixture::id(), 'producer', 2, TaskFixture::KEY_1)), TaskRejected::class);
        $fixture->onInputAdmission = static fn () => throw new RuntimeException('private policy');
        $this->reject(fn () => $fixture->supply([]), TaskRejected::class); self::assertNull($fixture->observe());
        $fixture->onInputAdmission = null; $fixture->onAdmission = static fn () => throw new RuntimeException('private producer');
        $this->reject(fn () => $fixture->issue(expectedVersion: 2), TaskRejected::class);
        $fixture->onAdmission = null; $fixture->updateAllowed = true; $fixture->submitAllowed = true; $fixture->continueAllowed = true;
        $fixture->record(TaskFixture::completed(), 2); $terminal = $fixture->get()->toString();
        $fixture->supply([TaskFixture::KEY_1 => false]); self::assertSame($terminal, $fixture->get()->toString());
        self::assertTrue($fixture->observe()->get('terminal'));
        $this->reject(fn () => $fixture->issue(expectedVersion: 3), TaskConflict::class);
        $fixture->now += 10000; self::assertNull($fixture->observe());
        $this->reject(fn () => $fixture->supply([]), TaskRejected::class);
        $fixture = $this->fixture(); $fixture->create();
        $this->reject(fn () => $fixture->commandBus->execute(new RequestTaskInput('scope', TaskFixture::id(), 'other', 1, self::batch())), TaskRejected::class);
        $this->reject(fn () => $fixture->commandBus->execute(new RetireTaskInput('scope', TaskFixture::id(), 'other', 1, TaskFixture::KEY_1)), TaskRejected::class);
    }

    public function test_that_envelope_retention_and_lifetime_bounds_reject_without_forgetting_old_keys(): void
    {
        $batch = self::batch(); $inputs = TaskInputs::empty('scope', TaskFixture::id());
        for ($index = 1; $index <= TaskInputs::MAX_KEYS; ++$index) { $inputs = $inputs->issue($batch, 0)->retire(TaskFixture::KEY_PREFIX.$index); }
        self::assertSame(TaskInputs::MAX_KEYS, $inputs->data()->get('issued'));
        $this->reject(fn () => $inputs->issue($batch, 0), DomainException::class);
        $full = TaskInputs::empty('scope', TaskFixture::id());
        for ($i = 0; $i < 16; ++$i) { $full = $full->issue($batch, 0); }
        $this->reject(fn () => $full->issue($batch, 0), DomainException::class);
        $two = TaskInputForms::batch(McpInputRequired::fromRequests(['a' => TaskFixture::form(), 'b' => TaskFixture::form()]), 'caller', 1000);
        $this->reject(fn () => $full->retire(TaskFixture::KEY_1)->issue($two, 0), DomainException::class);
        foreach ([StrictJson::fromData([]), StrictJson::fromObject(array_fill_keys(range('a', 'z'), null) + array_fill_keys(array_map(static fn ($n) => 'key'.$n, range(0, 40)), null)), StrictJson::fromObject(['unknown' => str_repeat('é', 17000)]), StrictJson::fromObject(['bad key' => false]), StrictJson::fromObject([1 => false])] as $envelope) {
            $this->reject(fn () => new SupplyTaskInput('scope', TaskFixture::id(), 'caller', $envelope), DomainException::class);
        }
        $fixture = $this->fixture(); $fixture->create();
        $huge = TaskInputForms::batch(McpInputRequired::fromRequests(['form' => TaskFixture::form(str_repeat('x', 1025))]), 'caller', $fixture->now + 1000);
        $this->reject(fn () => $fixture->commandBus->execute(new RequestTaskInput('scope', TaskFixture::id(), 'producer', 1, $huge)), TaskRejected::class);
        self::assertSame(1, $fixture->get()->get('version'));
    }

    public function test_that_corrupt_private_reconstruction_and_incoherent_producer_state_reject(): void
    {
        $batch = self::batch(); $inputs = TaskInputs::empty('scope', TaskFixture::id())->issue($batch, 0);
        self::assertSame($inputs->data()->toString(), TaskInputs::fromData($inputs->data())->data()->toString());
        $group = $inputs->data()->get('groups')->get(TaskFixture::KEY_1);
        $badGroups = [
            ['wrong-group' => $group],
            [TaskFixture::KEY_1 => $group->with('consumed', 'wrong')],
            [TaskFixture::KEY_1 => $group->with('answers', StrictJson::fromObject(['foreign' => (object) ['action' => 'accept']]))]
        ];
        foreach ($badGroups as $groups) { $this->reject(fn () => TaskInputs::fromData(StrictJson::fromObject(['namespace' => TaskFixture::KEY_NAMESPACE, 'issued' => 1, 'groups' => StrictJson::fromObject($groups)])), DomainException::class); }
        $this->reject(fn () => TaskInputs::fromData(StrictJson::fromObject(['namespace' => TaskFixture::KEY_NAMESPACE, 'issued' => 0, 'groups' => $inputs->data()->get('groups')])), DomainException::class);
        $this->reject(fn () => TaskInputs::fromData(StrictJson::fromObject()), DomainException::class);
        $this->reject(fn () => TaskInputBatch::fromData(StrictJson::fromObject()), DomainException::class);
        foreach ([StrictJson::fromObject(), StrictJson::fromData([]), StrictJson::fromObject(['bad key' => null]), StrictJson::fromObject(['form' => (object) ['public' => (object) [], 'contract' => null]])] as $requests) {
            $this->reject(fn () => TaskInputBatch::create('caller', 1, false, $requests), DomainException::class);
        }
        $large = StrictJson::fromObject(['a' => (object) ['name' => 'a', 'public' => (object) [], 'contract' => (object) ['private' => str_repeat('x', 24576)]]]);
        $this->reject(fn () => TaskInputBatch::create('caller', 1, false, $large), DomainException::class);
        $fixture = $this->fixture(); $fixture->create();
        $this->reject(fn () => $fixture->commandBus->execute(new \Fight\Common\Domain\Mcp\Task\Command\RecordTaskState('scope', TaskFixture::id(), 'producer', 1, TaskFixture::input())), TaskConflict::class);
        $fixture->issue(); $this->reject(fn () => $fixture->record(TaskState::create('working'), 2), TaskConflict::class);
        $task = $fixture->committedTaskReader->read('scope', TaskFixture::id());
        $this->reject(fn () => $task->transition('producer', 2, TaskFixture::input(), null, $fixture->now), DomainException::class);
        $different = $task->toArray(); $different['definition']['producer'] = 'other';
        $fixture->transactionalUnitOfWork->commitTransactional(fn () => $fixture->taskRepository->save(Task::fromArray($different)));
        $this->reject(fn () => $fixture->issue(expectedVersion: 2), TaskRejected::class);
        $this->reject(fn () => $fixture->commandBus->execute(new RetireTaskInput('scope', TaskFixture::id(), 'producer', 2, TaskFixture::KEY_1)), TaskRejected::class);
        $max = Task::fromArray(array_replace($task->toArray(), ['version' => Task::MAX_VERSION]));
        $this->reject(fn () => $max->retireInput(TaskFixture::KEY_1, Task::MAX_VERSION, $fixture->now), DomainException::class);
    }

    public function test_that_confirmation_requires_original_arguments_and_protected_observation_preserves_complete_context_depth(): void
    {
        $fixture = $this->fixture();
        $definition = TaskFixture::definition(operation: TaskFixture::definition()->operation()->with('arguments', null));
        $fixture->commandBus->execute(new CreateTask($definition, TaskState::create('working')));
        $this->reject(fn () => $fixture->issue(confirmation: true), TaskRejected::class);
        self::assertSame(1, $fixture->get()->get('version'));
        $fixture = $this->fixture(); $nested = 'leaf';
        for ($depth = 0; $depth < 63; ++$depth) { $nested = (object) ['node' => $nested]; }
        $operation = TaskFixture::definition()->operation()->with('retained', $nested, 64);
        $fixture->commandBus->execute(new CreateTask(TaskFixture::definition(operation: $operation), TaskState::create('working')));
        $fixture->issue(confirmation: true);
        self::assertSame($operation->toString(), $fixture->observe()->get('definition')->get('operation')->toString());
        $task = $fixture->committedTaskReader->read('scope', TaskFixture::id())->toArray();
        $task['inputs'] = TaskInputs::empty('other-scope', TaskFixture::id())->data();
        $this->reject(fn () => Task::fromArray($task), DomainException::class);
    }

    public function test_that_messages_and_handlers_preserve_owned_cqrs_contracts_and_reject_wrong_payloads(): void
    {
        $fixture = $this->fixture();
        $messages = [new RequestTaskInput('scope', TaskFixture::id(), 'producer', 1, self::batch()), new SupplyTaskInput('scope', TaskFixture::id(), 'caller', StrictJson::fromObject()), new AcquireTaskInput('scope', TaskFixture::id(), 'caller', TaskFixture::KEY_1), new RetireTaskInput('scope', TaskFixture::id(), 'producer', 1, TaskFixture::KEY_1), new GetTaskInput('scope', TaskFixture::id(), 'caller')];
        foreach ($messages as $message) { self::assertEquals($message->toArray(), $message::fromArray($message->toArray())->toArray()); }
        $wrong = CommandMessage::create(new CreateTask(TaskFixture::definition(), TaskState::create('working')));
        foreach ([$fixture->requestTaskInputHandler, $fixture->supplyTaskInputHandler, $fixture->acquireTaskInputHandler, $fixture->retireTaskInputHandler] as $commandHandler) {
            self::assertNotEmpty($commandHandler::commandRegistration());
            $this->reject(fn () => $commandHandler->handle($wrong), InvalidArgumentException::class);
        }
        self::assertSame(GetTaskInput::class, GetTaskInputHandler::queryRegistration());
        $this->reject(fn () => $fixture->getTaskInputHandler->handle(QueryMessage::create(new GetTask('scope', TaskFixture::id()))), InvalidArgumentException::class);
    }

    private static function accept(string $answer): array { return ['action' => 'accept', 'content' => (object) ['answer' => $answer]]; }
    private static function batch(): TaskInputBatch { return TaskInputForms::batch(McpInputRequired::fromRequests(['form' => TaskFixture::form()]), 'caller', 1000); }
    private function fixture(): TaskFixture { $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]); (new DbalTaskSchema())->install($connection); return new TaskFixture($connection); }
    private function reject(callable $operation, string $type): void
    {
        try { $operation(); self::fail('Expected '.$type); } catch (Throwable $failure) { self::assertInstanceOf($type, $failure); }
    }
}
