<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Task\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\TransactionIsolationLevel;
use Fight\Common\Adapter\Task\Dbal\DbalTaskSchema;
use Fight\Common\Adapter\Task\Dbal\DbalTaskStore;
use Fight\Common\Application\Task\CommandHandler\CreateTaskHandler;
use Fight\Common\Application\Task\CommandHandler\RecordTaskStateHandler;
use Fight\Common\Application\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Task\Exception\TaskConflict;
use Fight\Common\Application\Task\Exception\TaskRejected;
use Fight\Common\Application\Task\Exception\TaskStorageFailed;
use Fight\Common\Application\Task\QueryHandler\GetTaskHandler;
use Fight\Common\Application\Task\Service\TaskLifecycle;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Task\Command\CreateTask;
use Fight\Common\Domain\Task\Command\RecordTaskState;
use Fight\Common\Domain\Task\Event\TaskChanged;
use Fight\Common\Domain\Task\Query\GetTask;
use Fight\Common\Domain\Task\Task;
use Fight\Common\Domain\Task\TaskState;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(DbalTaskStore::class)]
#[CoversClass(DbalTaskSchema::class)]
#[CoversClass(TaskLifecycle::class)]
#[CoversClass(CreateTaskHandler::class)]
#[CoversClass(RecordTaskStateHandler::class)]
#[CoversClass(GetTaskHandler::class)]
#[CoversClass(TaskRejected::class)]
#[CoversClass(TaskConflict::class)]
#[CoversClass(TaskStorageFailed::class)]
#[CoversClass(CommittedTaskDispatchFailed::class)]
final class DbalTaskStoreTest extends UnitTestCase
{
    public function test_that_routed_lifecycle_commits_coherent_state_and_rechecks_current_independent_permissions(): void
    {
        $connection = $this->database(); $fixture = new TaskFixture($connection); $facts = [];
        $fixture->events->addHandler(ClassName::underscore(TaskChanged::class), static function (EventMessage $message) use (&$facts, $connection): void {
            self::assertFalse($connection->isTransactionActive()); $facts[] = $message->payload()->toArray();
        });
        $fixture->create(1000, 25); $fixture->create(1000, 25);
        self::assertSame(2, $fixture->admissions); self::assertCount(1, $facts);
        $initial = $fixture->get(); self::assertSame('working', $initial->get('state')->get('status'));
        self::assertSame(25, $initial->get('poll_interval'));
        self::assertNull($fixture->get(str_repeat('f', 32))); self::assertNull($fixture->get(scope: 'other'));
        $fixture->createAllowed = false; $fixture->updateAllowed = false;
        foreach ([fn () => $fixture->create(1000, 25), fn () => $fixture->record(TaskFixture::completed())] as $operation) {
            try { $operation(); self::fail('Permission required independently.'); } catch (TaskRejected $failure) { self::assertSame('Task operation rejected.', $failure->getMessage()); }
        }
        self::assertSame($initial->toString(), $fixture->get()->toString());
        $fixture->readAllowed = false; self::assertNull($fixture->get());
        $fixture->readAllowed = true; $fixture->discloseAllowed = false; self::assertNull($fixture->get());
        $fixture->discloseAllowed = true; $fixture->updateAllowed = true; $fixture->createAllowed = true;
        $fixture->now += 2; $fixture->record(TaskFixture::input());
        self::assertSame('input_required', $fixture->get()->get('state')->get('status'));
        $fixture->record(TaskFixture::completed(true), 2);
        self::assertSame('completed', $fixture->get()->get('state')->get('status'));
        self::assertTrue($fixture->get()->get('state')->get('result')->get('isError'));
        self::assertCount(3, $facts);
        foreach ([fn () => $fixture->record(TaskState::create('working'), 3), fn () => $fixture->record(TaskFixture::completed(), 1), fn () => $fixture->create(999, 25)] as $operation) {
            try { $operation(); self::fail('Conflict required.'); } catch (TaskConflict $failure) { self::assertSame('Task state conflicts.', $failure->getMessage()); }
        }
        $fixture->create(1000, 25); self::assertSame(3, $fixture->get()->get('version')); self::assertCount(3, $facts);
        $fixture->now += 1000; self::assertNull($fixture->get());
        foreach ([fn () => $fixture->create(1000, 25), fn () => $fixture->record(TaskFixture::completed(true), 2)] as $operation) {
            try { $operation(); self::fail('Expiry must not renew retention.'); } catch (TaskRejected) { self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_tasks')); }
        }
    }

    public function test_that_missing_wrong_producer_and_unsafe_payloads_share_safe_rejection_without_mutation(): void
    {
        $connection = $this->database(); $fixture = new TaskFixture($connection);
        try { $fixture->record(TaskFixture::completed()); self::fail('Missing cannot complete.'); } catch (TaskRejected) { self::assertNull($fixture->get()); }
        $fixture->create(); $before = $fixture->get()->toString();
        foreach ([new RecordTaskState('scope', TaskFixture::ID, 'other', 1, TaskFixture::completed()), new RecordTaskState('scope', TaskFixture::ID, 'producer', 1, TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'private diagnostic']))), new RecordTaskState('scope', TaskFixture::ID, 'producer', 1, TaskState::create('completed', result: StrictJson::fromObject(['content' => [], 'structuredContent' => (object) ['ok' => 'wrong'], 'isError' => false])))] as $command) {
            try { $fixture->commands->execute($command); self::fail('Unsafe evidence must reject.'); } catch (TaskRejected) { self::assertSame($before, $fixture->get()->toString()); }
        }
        // Tampered but structurally valid state cannot bypass original contract validation on reads.
        $data = $fixture->store->read('scope', TaskFixture::ID)->toArray();
        $data['definition']['operation'] = StrictJson::fromObject(['kind' => 'unknown']);
        $connection->update('fight_tasks', ['payload' => json_encode($data, JSON_THROW_ON_ERROR)], ['scope' => 'scope']);
        $this->expectException(TaskRejected::class); $fixture->get();
    }

    public function test_that_postcommit_dispatch_failure_retains_state_and_retry_does_not_replay_facts(): void
    {
        $connection = $this->database(); $fixture = new TaskFixture($connection); $calls = 0;
        $fixture->events->addHandler(ClassName::underscore(TaskChanged::class), static function () use (&$calls): void { ++$calls; throw new RuntimeException('private'); });
        try { $fixture->create(); self::fail('Expected committed failure.'); } catch (CommittedTaskDispatchFailed $failure) {
            self::assertSame('Task committed; fact dispatch failed.', $failure->getMessage()); self::assertSame(1, $failure->event()->toArray()['version']);
        }
        $fixture->create(); self::assertSame(1, $calls); self::assertSame(1, $fixture->get()->get('version'));
    }

    public function test_that_outer_transactions_nested_use_and_unowned_writes_leave_caller_work_untouched(): void
    {
        $connection = $this->database(); $fixture = new TaskFixture($connection);
        $connection->executeStatement('CREATE TABLE caller_work (id INTEGER)');
        foreach ([fn () => $fixture->store->save((new CreateTask(TaskFixture::definition(), TaskState::create('working')))->at(0)), fn () => $fixture->store->getById('scope', TaskFixture::ID), fn () => $fixture->store->commitTransactional(fn () => $fixture->store->read('scope', TaskFixture::ID)), fn () => $fixture->store->commitTransactional(fn () => $fixture->store->commitTransactional(static fn () => null))] as $operation) {
            try { $operation(); self::fail('Ownership required.'); } catch (LogicException) { self::assertFalse($connection->isTransactionActive()); }
        }
        $connection->beginTransaction(); $connection->insert('caller_work', ['id' => 7]);
        foreach ([fn () => $fixture->create(), fn () => new DbalTaskStore($connection)] as $operation) {
            try { $operation(); self::fail('Outer transaction unsupported.'); } catch (LogicException) { self::assertTrue($connection->isTransactionActive()); self::assertSame(7, (int) $connection->fetchOne('SELECT id FROM caller_work')); }
        }
        self::assertSame(0, $fixture->admissions); $connection->rollBack();
        $connection->setTransactionIsolation(TransactionIsolationLevel::READ_UNCOMMITTED);
        $this->expectException(LogicException::class); $fixture->create();
    }

    public function test_that_constraint_failure_rolls_back_atomic_state_and_allows_explicit_retry(): void
    {
        $connection = $this->database(); $fixture = new TaskFixture($connection); $fixture->create();
        $before = $fixture->get()->toString();
        $connection->executeStatement("CREATE TRIGGER fail_task BEFORE INSERT ON fight_tasks BEGIN SELECT RAISE(ABORT, 'private'); END");
        try { $fixture->record(TaskFixture::completed()); self::fail('Expected failure.'); } catch (TaskStorageFailed $failure) { self::assertFalse($failure->outcomeUncertain()); self::assertSame('Task storage failed.', $failure->getMessage()); }
        self::assertSame($before, $fixture->get()->toString()); self::assertFalse($fixture->store->isClosed());
        $connection->executeStatement('DROP TRIGGER fail_task'); $fixture->record(TaskFixture::completed()); self::assertSame(2, $fixture->get()->get('version'));
    }

    #[DataProvider('uncertainties')]
    public function test_that_uncertain_begin_commit_and_rollback_close_without_false_success(string $stage, bool $active): void
    {
        $connection = $this->mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->andReturn(new PostgreSQLPlatform());
        $connection->shouldReceive('getTransactionIsolation')->andReturn(TransactionIsolationLevel::READ_COMMITTED);
        $connection->shouldReceive('isTransactionActive')->andReturn(false, false, $active);
        $connection->shouldReceive('getTransactionNestingLevel')->andReturn(1);
        $cause = new RuntimeException('private storage diagnostic');
        if ($stage === 'begin') { $connection->shouldReceive('beginTransaction')->once()->andThrow($cause); }
        else {
            $connection->shouldReceive('beginTransaction')->once();
            $connection->shouldReceive('executeStatement')->once(); $connection->shouldReceive('fetchOne')->once()->andReturn(1);
            if ($stage === 'rollback') { $connection->shouldReceive('rollBack')->once()->andThrow(new RuntimeException('cleanup')); }
            else { $connection->shouldReceive('commit')->once()->andThrow($cause); if ($active) { $connection->shouldReceive('rollBack')->once(); } }
        }
        $store = new DbalTaskStore($connection);
        try { $store->commitTransactional(static function () use ($stage, $cause) { if ($stage === 'rollback') { throw $cause; } return null; }); self::fail('Uncertainty required.'); }
        catch (TaskStorageFailed $failure) { self::assertTrue($failure->outcomeUncertain()); self::assertSame($cause, $failure->getPrevious()); }
        self::assertTrue($store->isClosed()); $this->expectException(LogicException::class); $store->read('scope', TaskFixture::ID);
    }

    public static function uncertainties(): iterable
    {
        yield ['begin', false]; yield ['commit', false]; yield ['commit', true]; yield ['rollback', false];
    }

    #[DataProvider('corruptions')]
    public function test_that_corrupt_storage_is_not_missing_or_successful_empty_data(string $payload): void
    {
        $connection = $this->database(); $fixture = new TaskFixture($connection); $fixture->create();
        $connection->update('fight_tasks', ['payload' => $payload], ['scope' => 'scope']);
        $this->expectException(TaskStorageFailed::class); $fixture->get();
    }

    public static function corruptions(): iterable
    {
        yield ['null']; yield ['{']; yield ['{}']; yield ['[]'];
        yield ['{"definition":null,"state":null}']; yield [str_repeat('x', 65537)];
        $task = (new CreateTask(TaskFixture::definition(), TaskState::create('working')))->at(1)->toArray();
        $task['definition']['id'] = str_repeat('f', 32);
        $task['inputs'] = \Fight\Common\Domain\Task\TaskInputs::empty('scope', str_repeat('f', 32))->data();
        yield [json_encode($task, JSON_THROW_ON_ERROR)];
    }

    public function test_that_schema_is_exclusive_idempotent_and_supports_only_the_documented_backends(): void
    {
        $connection = $this->database(); $schema = new DbalTaskSchema(); $schema->install($connection);
        foreach ([new MySQLPlatform(), new PostgreSQLPlatform()] as $platform) {
            $empty = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])->createSchemaManager()->introspectSchema();
            $schema->addTo($empty, $platform); self::assertCount(2, $empty->getTables()); self::assertNotEmpty($empty->toSql($platform));
        }
        $connection->beginTransaction();
        try { $schema->install($connection); self::fail('Exclusive setup required.'); } catch (RuntimeException) { self::assertTrue($connection->isTransactionActive()); }
        $connection->rollBack(); $connection->update('fight_task_state', ['version' => 2], ['singleton' => 1]);
        foreach ([fn () => $schema->install($connection), fn () => (new TaskFixture($connection))->create()] as $operation) {
            try { $operation(); self::fail('Unsupported schema required.'); } catch (RuntimeException) { self::assertFalse($connection->isTransactionActive()); }
        }
        $connection->executeStatement('DROP TABLE fight_tasks');
        try { $schema->install($connection); self::fail('Partial namespace must reject.'); } catch (RuntimeException) { self::assertTrue(true); }
        $unsupported = $this->mock(Connection::class); $unsupported->shouldReceive('getDatabasePlatform')->andReturn(new OraclePlatform());
        $this->expectException(InvalidArgumentException::class); new DbalTaskStore($unsupported);
    }

    public function test_that_handler_registrations_reject_unrelated_messages(): void
    {
        $fixture = new TaskFixture($this->database());
        self::assertSame(CreateTask::class, CreateTaskHandler::commandRegistration());
        self::assertSame(RecordTaskState::class, RecordTaskStateHandler::commandRegistration());
        self::assertSame(GetTask::class, GetTaskHandler::queryRegistration());
        foreach ([fn () => (new CreateTaskHandler($fixture->lifecycle))->handle(CommandMessage::create(new RecordTaskState('scope', TaskFixture::ID, 'producer', 1, TaskState::create('working')))), fn () => (new RecordTaskStateHandler($fixture->lifecycle))->handle(CommandMessage::create(new CreateTask(TaskFixture::definition(), TaskState::create('working')))), fn () => (new GetTaskHandler($fixture->lifecycle))->handle(QueryMessage::create(new \Fight\Common\Domain\Notice\Query\GetNotice('scope', 'producer', 'notice')))] as $operation) {
            try { $operation(); self::fail('Wrong message must reject.'); } catch (InvalidArgumentException) { self::assertTrue(true); }
        }
    }

    public function test_that_accepted_original_context_depth_is_reconstructable_after_creation(): void
    {
        $fixture = new TaskFixture($this->database());
        $nested = 'leaf';
        for ($depth = 0; $depth < 63; ++$depth) { $nested = (object) ['node' => $nested]; }
        $operation = TaskFixture::definition()->operation()->with('retained', $nested, 64);
        $fixture->commands->execute(new CreateTask(TaskFixture::definition(operation: $operation), TaskState::create('working')));
        self::assertSame('working', $fixture->get()->get('state')->get('status'));
        self::assertSame($operation->toString(), $fixture->store->read('scope', TaskFixture::ID)->definition()->operation()->toString());
    }

    public function test_that_creation_retries_keep_original_expiry_at_the_timestamp_ceiling(): void
    {
        $fixture = new TaskFixture($this->database());
        $fixture->now = Task::MAX_TIME - 100;
        $fixture->create(100);
        $before = $fixture->get()->toString();
        $fixture->now = Task::MAX_TIME - 1;
        $fixture->create(100);
        self::assertSame($before, $fixture->get()->toString());
        $fixture->now = Task::MAX_TIME;
        self::assertNull($fixture->get());
        $this->expectException(TaskRejected::class);
        $fixture->create(100);
    }

    public function test_that_complete_stored_representation_has_an_independent_atomic_byte_bound(): void
    {
        $connection = $this->database(); $store = new DbalTaskStore($connection);
        $operation = StrictJson::fromObject(['padding' => str_repeat('x', 8178)]);
        self::assertSame(8192, strlen($operation->toString()));
        $definition = \Fight\Common\Domain\Task\TaskDefinition::create(TaskFixture::ID, 'scope', 'producer', $operation);
        $task = Task::create($definition, TaskState::create('working'), null, null, 0);
        $empty = TaskState::create('completed', result: StrictJson::fromObject(['body' => '']));
        $capacity = TaskState::MAX_BYTES - strlen($empty->data()->toString());
        $large = TaskState::create('completed', result: StrictJson::fromObject(['body' => str_repeat('x', $capacity)]));
        $next = $task->transition('producer', 1, $large, null, 1);
        try { $store->commitTransactional(fn () => $store->save($next)); self::fail('Combined encoded bound must reject.'); }
        catch (RuntimeException) { self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_tasks')); }
        $store->commitTransactional(fn () => $store->save($task));
        self::assertSame(8192, strlen($store->read('scope', TaskFixture::ID)->definition()->operation()->toString()));
    }

    private function database(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new DbalTaskSchema())->install($connection); return $connection;
    }
}
