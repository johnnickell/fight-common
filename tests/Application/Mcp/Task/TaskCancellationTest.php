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
use Fight\Common\Application\Mcp\Task\Service\TaskCancellationLifecycle;
use Fight\Common\Application\Mcp\Task\Service\TaskStore;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Command\CreateTask;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\Query\GetTask;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Task;
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
        $f = $this->fixture(); $f->create(1000, 15); $f->issue(confirmation: true);
        $before = $f->store->read('scope', TaskFixture::ID); $events = [];
        $f->events->addHandler(ClassName::underscore(TaskChanged::class), static function ($message) use ($f, &$events): void {
            self::assertNotNull($f->store->read('scope', TaskFixture::ID)->cancellation());
            $events[] = $message->payload()->toArray();
        });
        self::assertNull($f->cancellation()->get('cancellation'));
        $f->now += 1; $f->cancel();
        $intent = $f->cancellation()->get('cancellation');
        self::assertSame(['requested_at' => $f->now, 'version' => 3], $intent->properties());
        self::assertSame($before->state()->data()->toString(), $f->get()->get('state')->toString());
        self::assertArrayNotHasKey('cancellation', $f->get()->properties());
        self::assertSame(1000, $f->get()->get('ttl')); self::assertSame(15, $f->get()->get('poll_interval'));
        $f->now += 5; $f->cancel(); self::assertSame(3, $f->get()->get('version')); self::assertCount(1, $events);
        self::assertSame($intent->toString(), $f->cancellation()->get('cancellation')->toString());
        $f->supply([TaskFixture::KEY_1 => ['action' => 'accept', 'content' => (object) ['answer' => 'accepted']]]);
        $f->acquire();
        self::assertTrue($f->observe()->get('groups')->get(TaskFixture::KEY_1)->get('consumed'));
        self::assertSame($intent->toString(), $f->observe()->get('cancellation')->toString());
        $f->record(TaskFixture::completed(), 5);
        self::assertSame($intent->toString(), $f->cancellation()->get('cancellation')->toString());
        $terminal = $f->get()->toString(); $f->cancel(); self::assertSame($terminal, $f->get()->toString());
        self::assertSame('completed', $f->get()->get('state')->get('status'));
        $f->now = $before->view()['created_at'] + 1000;
        self::assertNull($f->cancellation()); $this->reject(fn () => $f->cancel(), TaskRejected::class);
    }

    public function test_that_each_terminal_outcome_is_an_authorized_noop_without_new_intent_or_payload_change(): void
    {
        foreach ([TaskFixture::completed(), TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])), TaskState::create('cancelled')] as $state) {
            $f = $this->fixture(); $f->create(); $f->record($state); $before = $f->store->read('scope', TaskFixture::ID)->toArray();
            $f->events->addHandler(ClassName::underscore(TaskChanged::class), static fn () => self::fail('No new cancellation fact'));
            $f->cancel(); self::assertEquals($before, $f->store->read('scope', TaskFixture::ID)->toArray());
            self::assertNull($f->cancellation()->get('cancellation'));
            $f->cancelAllowed = false; $this->reject(fn () => $f->cancel(), TaskRejected::class);
        }
    }

    public function test_that_independent_current_permissions_and_safe_absence_precede_protected_lookup_and_continuation(): void
    {
        $f = $this->fixture(); self::assertNull($f->cancellation()); $this->reject(fn () => $f->cancel(), TaskRejected::class);
        $f->create(); $f->issue(confirmation: true); $f->supply([TaskFixture::KEY_1 => ['action' => 'accept', 'content' => (object) ['answer' => 'yes']]]);
        $f->cancelAllowed = false; $this->reject(fn () => $f->cancel(), TaskRejected::class);
        self::assertNull($f->cancellation()->get('cancellation'));
        $f->cancelAllowed = true; $f->readAllowed = false; $f->updateAllowed = false; $f->cancelObserveAllowed = false;
        $f->cancel(); self::assertNull($f->get()); self::assertNull($f->cancellation()); self::assertNull($f->observe());
        $this->reject(fn () => $f->acquire(), TaskRejected::class);
        $f->cancelObserveAllowed = true; self::assertNotNull($f->observe()->get('cancellation'));
        $f->continueAllowed = false; $this->reject(fn () => $f->acquire(), TaskRejected::class);
        $f->cancelAllowed = false; $this->reject(fn () => $f->cancel(), TaskRejected::class);
        $f->cancelAllowed = true;
        self::assertNull($f->cancellation(scope: 'other'));
        $this->reject(fn () => $f->cancel(scope: 'other'), TaskRejected::class);
        self::assertNull($f->cancellation(id: str_repeat('f', 32)));
        $this->reject(fn () => $f->cancel(id: str_repeat('f', 32)), TaskRejected::class);
        $f->onCancellationAdmission = static fn () => throw new RuntimeException('private policy');
        self::assertNull($f->cancellation()); self::assertNull($f->observe());
        $this->reject(fn () => $f->cancel(), TaskRejected::class);
        $f->onCancellationAdmission = null; $f->remove(); self::assertNull($f->cancellation());
        $this->reject(fn () => $f->cancel(), TaskRejected::class);
    }

    public function test_that_domain_fences_preserve_intent_and_reject_corrupt_or_exhausted_admission_evidence(): void
    {
        $task = Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 10);
        $intent = $task->requestCancellation(1); self::assertSame(10, $intent->cancellation()->get('requested_at'));
        self::assertNull($intent->requestCancellation(12));
        $updated = $intent->transition('producer', 2, TaskFixture::completed(), null, 20);
        self::assertSame($intent->cancellation()->toString(), Task::fromArray($updated->toArray())->cancellation()->toString());
        $max = Task::fromArray(array_replace($task->toArray(), ['version' => Task::MAX_VERSION, 'last_digest' => str_repeat('a', 64)]));
        $this->reject(fn () => $max->requestCancellation(10), DomainException::class);
        foreach ([['cancellation_requested_at' => 9, 'cancellation_version' => 2], ['cancellation_requested_at' => 11, 'cancellation_version' => 2], ['cancellation_requested_at' => 10, 'cancellation_version' => 1], ['cancellation_requested_at' => 10, 'cancellation_version' => 3], ['cancellation_version' => null], ['cancellation_requested_at' => null]] as $patch) {
            $this->reject(fn () => Task::fromArray(array_replace($intent->toArray(), $patch)), DomainException::class);
        }
        $f = $this->fixture(); $f->create(); $f->store->commitTransactional(fn () => $f->store->save($max));
        $this->reject(fn () => $f->cancel(), TaskConflict::class);
    }

    public function test_that_postcommit_failure_and_unavailable_reconstruction_never_claim_rollback_or_stopping(): void
    {
        $f = $this->fixture(); $f->create(); $calls = 0;
        $f->events->addHandler(ClassName::underscore(TaskChanged::class), static function () use (&$calls): void { ++$calls; throw new RuntimeException('private delivery'); });
        $this->reject(fn () => $f->cancel(), CommittedTaskDispatchFailed::class);
        self::assertNotNull($f->cancellation()->get('cancellation')); $f->cancel(); self::assertSame(1, $calls);
        $initial = Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, $f->now);
        foreach ([null, $initial, new TaskStorageFailed(false, new RuntimeException('Read failed after commit'))] as $reconstructed) {
            $store = $this->mock(TaskStore::class);
            $store->shouldReceive('commitTransactional')->once()->andReturnUsing(static fn ($operation) => $operation());
            $store->shouldReceive('getById')->once()->with('scope', TaskFixture::ID)->andReturn($initial);
            $store->shouldReceive('save')->once();
            if ($reconstructed instanceof Throwable) { $store->shouldReceive('read')->once()->andThrow($reconstructed); }
            else { $store->shouldReceive('read')->once()->andReturn($reconstructed); }
            $service = new TaskCancellationLifecycle($store, $f, $f->events, fn () => $f->now);
            try { $service->request(new RequestTaskCancellation('scope', TaskFixture::ID, 'caller')); self::fail('No acknowledgment without reconstruction'); }
            catch (TaskStorageFailed $failure) { self::assertTrue($failure->outcomeUncertain()); }
        }
    }

    public function test_that_command_query_envelopes_and_handlers_keep_existing_bus_contracts(): void
    {
        $f = $this->fixture();
        foreach ([new RequestTaskCancellation('scope', TaskFixture::ID, 'caller'), new GetTaskCancellation('scope', TaskFixture::ID, 'caller')] as $message) {
            self::assertSame($message->toArray(), $message::fromArray($message->toArray())->toArray());
        }
        self::assertSame(RequestTaskCancellation::class, RequestTaskCancellationHandler::commandRegistration());
        self::assertSame(GetTaskCancellation::class, GetTaskCancellationHandler::queryRegistration());
        $this->reject(fn () => (new RequestTaskCancellationHandler($f->cancellationLifecycle))->handle(CommandMessage::create(new CreateTask(TaskFixture::definition(), TaskState::create('working')))), InvalidArgumentException::class);
        $this->reject(fn () => (new GetTaskCancellationHandler($f->cancellationLifecycle))->handle(QueryMessage::create(new GetTask('scope', TaskFixture::ID))), InvalidArgumentException::class);
    }

    private function fixture(): TaskFixture { $c = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]); (new DbalTaskSchema())->install($c); return new TaskFixture($c); }
    private function reject(callable $operation, string $type): void { try { $operation(); self::fail('Expected '.$type); } catch (Throwable $failure) { self::assertInstanceOf($type, $failure); } }
}
