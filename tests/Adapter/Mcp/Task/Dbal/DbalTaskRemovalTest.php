<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Task\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Task\Dbal\DbalTaskSchema;
use Fight\Common\Adapter\Mcp\Task\Dbal\DbalTaskStore;
use Fight\Common\Application\Mcp\Task\CommandHandler\RemoveTasksHandler;
use Fight\Common\Application\Mcp\Task\Exception\CommittedTaskDispatchFailed;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Mcp\Task\Exception\TaskStorageFailed;
use Fight\Common\Application\Mcp\Task\Service\TaskLifecycle;
use Fight\Common\Domain\Mcp\Task\Command\CreateTask;
use Fight\Common\Domain\Mcp\Task\Command\RemoveTasks;
use Fight\Common\Domain\Mcp\Task\Event\TaskDataRemoved;
use Fight\Common\Domain\Mcp\Task\TaskSelection;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(DbalTaskStore::class)]
#[CoversClass(TaskLifecycle::class)]
#[CoversClass(RemoveTasksHandler::class)]
#[CoversClass(CommittedTaskDispatchFailed::class)]
final class DbalTaskRemovalTest extends UnitTestCase
{
    public function test_that_all_grants_precede_deletion_and_missing_repeats_recheck_current_permission(): void
    {
        $connection = $this->database();
        $fixture = new TaskFixture($connection);
        $other = str_repeat('a', 32);
        $fixture->create(); $fixture->create(id: $other);
        $fixture->deniedRemovalIds = [$other];
        try { $fixture->remove([TaskFixture::ID, $other]); self::fail('Entire selection must reject.'); }
        catch (TaskRejected $failure) { self::assertSame('Task operation rejected.', $failure->getMessage()); }
        self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_tasks'));
        self::assertSame([['scope', TaskFixture::ID], ['scope', $other]], $fixture->removalChecks);
        $fixture->deniedRemovalIds = [];
        $fixture->createAllowed = false; $fixture->updateAllowed = false; $fixture->readAllowed = false;
        $fixture->remove();
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_tasks'));
        $fixture->remove();
        $fixture->removeAllowed = false;
        try { $fixture->remove(); self::fail('Missing repeat cannot bypass revocation.'); }
        catch (TaskRejected) { self::assertCount(5, $fixture->removalChecks); }
        $fixture->removeAllowed = true;
        try { $fixture->remove([$other], 'SUPER_ADMIN'); self::fail('Role label is not scope authority.'); }
        catch (TaskRejected) { self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_tasks')); }
        $fixture->onAdmission = static function (): void { throw new RuntimeException('private denial'); };
        try { $fixture->remove([$other]); self::fail('Admission failure must reject.'); }
        catch (TaskRejected $failure) { self::assertNull($failure->getPrevious()); self::assertStringNotContainsString('private', $failure->getMessage()); }
    }

    public function test_that_only_actual_committed_deletions_emit_one_bounded_fact_and_dispatch_failure_cannot_restore_data(): void
    {
        $connection = $this->database(); $fixture = new TaskFixture($connection); $fixture->create(); $facts = [];
        $fixture->events->addHandler(ClassName::underscore(TaskDataRemoved::class), static function (EventMessage $message) use ($connection, &$facts): void {
            self::assertFalse($connection->isTransactionActive());
            self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_tasks'));
            $facts[] = $message->payload()->toArray();
            throw new RuntimeException('private subscriber failure');
        });
        try { $fixture->remove([str_repeat('b', 32), TaskFixture::ID]); self::fail('Committed failure must remain observable.'); }
        catch (CommittedTaskDispatchFailed $failure) {
            self::assertInstanceOf(TaskDataRemoved::class, $failure->event());
            self::assertSame(['scope' => 'scope', 'ids' => [TaskFixture::ID]], $failure->event()->toArray());
            self::assertSame('Task committed; fact dispatch failed.', $failure->getMessage());
        }
        self::assertNull($fixture->get()); $fixture->remove(); self::assertCount(1, $facts);
        try { $fixture->record(TaskFixture::completed()); self::fail('Late producer cannot upsert.'); }
        catch (TaskRejected) { self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_tasks')); }
        $fixture->now += 100;
        $fixture->create(200);
        self::assertSame(1, $fixture->get()->get('version'));
        self::assertSame($fixture->now, $fixture->get()->get('created_at'));
    }

    public function test_that_second_delete_failure_rolls_back_the_complete_selection_without_a_fact(): void
    {
        $connection = $this->database(); $fixture = new TaskFixture($connection); $other = str_repeat('a', 32);
        $fixture->create(); $fixture->record(TaskFixture::input()); $fixture->create(id: $other);
        $before = $connection->fetchAllAssociative('SELECT * FROM fight_tasks ORDER BY task_id');
        $facts = 0;
        $fixture->events->addHandler(ClassName::underscore(TaskDataRemoved::class), static function () use (&$facts): void { ++$facts; });
        $connection->executeStatement("CREATE TRIGGER fail_removal BEFORE DELETE ON fight_tasks WHEN OLD.task_id = '".$other."' BEGIN SELECT RAISE(ABORT, 'private'); END");
        try { $fixture->remove([TaskFixture::ID, $other]); self::fail('Atomic removal must fail.'); }
        catch (TaskStorageFailed $failure) { self::assertFalse($failure->outcomeUncertain()); }
        self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM fight_tasks ORDER BY task_id'));
        self::assertSame(0, $facts); self::assertFalse($fixture->store->isClosed());
        $connection->executeStatement('DROP TRIGGER fail_removal'); $fixture->remove([TaskFixture::ID, $other]);
        self::assertSame(1, $facts); self::assertNull($fixture->get());
    }

    public function test_that_removal_rejects_unowned_and_enclosing_transactions_before_admission_or_caller_mutation(): void
    {
        $connection = $this->database(); $fixture = new TaskFixture($connection); $fixture->create();
        try { $fixture->store->remove(TaskSelection::fromIds('scope', [TaskFixture::ID])); self::fail('Owned transaction required.'); }
        catch (LogicException) { self::assertNotNull($fixture->get()); }
        $connection->executeStatement('CREATE TABLE caller_work (id INTEGER)');
        $connection->beginTransaction(); $connection->insert('caller_work', ['id' => 7]);
        try { $fixture->remove(); self::fail('Caller transaction must not be enlisted.'); }
        catch (LogicException) { self::assertSame(1, $fixture->admissions); self::assertTrue($connection->isTransactionActive()); self::assertSame(7, (int) $connection->fetchOne('SELECT id FROM caller_work')); }
        $connection->rollBack(); self::assertNotNull($fixture->get());
    }

    public function test_that_removal_cleans_expired_unlimited_and_corrupt_rows_without_reading_protected_payloads(): void
    {
        $connection = $this->database(); $fixture = new TaskFixture($connection); $other = str_repeat('a', 32);
        $fixture->create(10); $fixture->create(id: $other); $fixture->now += 10;
        self::assertNull($fixture->get()); self::assertNotNull($fixture->get($other));
        self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_tasks'));
        $connection->update('fight_tasks', ['payload' => 'corrupt private payload'], ['task_id' => $other]);
        $fixture->remove([TaskFixture::ID, $other]);
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM fight_tasks'));
    }

    public function test_that_handler_registration_rejects_an_unrelated_command(): void
    {
        $fixture = new TaskFixture($this->database());
        self::assertSame(RemoveTasks::class, RemoveTasksHandler::commandRegistration());
        $this->expectException(InvalidArgumentException::class);
        (new RemoveTasksHandler($fixture->lifecycle))->handle(CommandMessage::create(new CreateTask(TaskFixture::definition(), TaskState::create('working'))));
    }

    private function database(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new DbalTaskSchema())->install($connection);
        return $connection;
    }
}
