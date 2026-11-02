<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Task;

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Task\Dbal\DbalTaskSchema;
use Fight\Common\Application\Mcp\Task\McpTasks;
use Fight\Common\Application\Mcp\Task\Service\TaskLifecycle;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskInputs;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\Fixture\Mcp\TaskEndpoint;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Task::class)]
#[CoversClass(TaskInputs::class)]
#[CoversClass(TaskLifecycle::class)]
#[CoversClass(McpTasks::class)]
final class TaskRevisionTest extends UnitTestCase
{
    public function test_that_equal_timestamps_private_mutations_and_reconstruction_do_not_leak_mutation_gaps(): void
    {
        $task = Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 10);
        $first = $task->revision('visibility', 10);
        $cancel = $task->requestCancellation(10);
        self::assertSame(2, $cancel->version());
        self::assertSame($first->toString(), $cancel->revision('visibility', 10)->toString());
        $next = $cancel->transition('producer', 2, TaskFixture::completed(true), null, 10);
        self::assertGreaterThan($first->get('value'), $next->revision('visibility', 10)->get('value'));
        self::assertSame(2, $next->toArray()['revision']->get('counter'));
        self::assertSame($first->get('scope'), $next->revision('visibility', 10)->get('scope'));
        self::assertSame($next->revision('visibility', 10)->toString(), Task::fromArray($next->toArray())->revision('visibility', 10)->toString());
        self::assertNotSame($first->get('scope'), $next->revision('different-visibility', 10)->get('scope'));
        self::assertNotSame($first->get('scope'), Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 10)->revision('visibility', 10)->get('scope'));
    }

    public function test_that_input_expiry_and_later_mutations_never_reuse_an_equal_revision_for_incompatible_state(): void
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]); (new DbalTaskSchema())->install($db);
        $f = new TaskFixture($db); $f->now = 10; $f->create(); $f->issue(expiresAt: 100); $f->issue(expectedVersion: 2, expiresAt: 200);
        $old = $f->get(); $f->now = 100; $partial = $f->get();
        self::assertCount(1, $partial->get('state')->get('input')->properties());
        self::assertGreaterThan($old->get('revision')->get('value'), $partial->get('revision')->get('value'));
        $f->cancel(); $after = $f->get();
        self::assertGreaterThan($partial->get('revision')->get('value'), $after->get('revision')->get('value'));
        $f->now = 200; $expired = $f->get();
        self::assertSame('working', $expired->get('state')->get('status'));
        self::assertGreaterThan($after->get('revision')->get('value'), $expired->get('revision')->get('value'));
        $f->record(TaskFixture::completed(), expectedVersion: 4);
        self::assertGreaterThan($expired->get('revision')->get('value'), $f->get()->get('revision')->get('value'));
        self::assertSame(3, $old->get('version'));
    }

    public function test_that_a_later_committed_write_cannot_reuse_a_revision_observed_across_input_expiry_on_another_clock(): void
    {
        $task = Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 0);
        $task = $task->requestInput(\Fight\Common\Application\Mcp\Task\Service\TaskInputForms::batch(\Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired::fromRequests(['answer' => TaskFixture::form()]), 'caller', 100), 1, 0);
        $expired = $task->revision('visibility', 100);
        self::assertSame('working', $task->view(100)['state']->get('status'));
        // Independent clocks can straddle a deadline by 1 ms without either process moving backwards.
        $later = $task->transition('producer', 2, TaskState::create('input_required', 'New producer state', $task->view(99)['state']->get('input')), null, 99);
        self::assertSame('input_required', $later->view(99)['state']->get('status'));
        $newer = $later->revision('visibility', 99);
        self::assertSame($expired->get('scope'), $newer->get('scope'));
        self::assertGreaterThan($expired->get('value'), $newer->get('value'));
    }

    public function test_that_final_current_read_checks_follow_projection_context_and_clock_callbacks(): void
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]); (new DbalTaskSchema())->install($db);
        $f = new TaskFixture($db); $f->create(ttl: 100); $endpoint = new TaskEndpoint($f);
        $f->onComparison = function () use ($f): void { $f->readAllowed = false; };
        self::assertNull($f->get()); self::assertNull($endpoint->tasks->current(TaskFixture::ID));
        $f->readAllowed = true; $f->onComparison = function () use ($f): void { $f->discloseAllowed = false; };
        self::assertNull($f->get());
        $f->discloseAllowed = true; $f->onComparison = function () use ($f): void { $f->now += 100; };
        self::assertNull($f->get());
    }

    public function test_that_corrupt_exhausted_and_clock_rollback_revisions_fail_without_fabricating_comparison(): void
    {
        $task = Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 10);
        foreach ([['incarnation' => 'bad', 'counter' => 1], ['incarnation' => str_repeat('a', 32), 'counter' => 0], ['incarnation' => str_repeat('a', 32), 'counter' => Task::MAX_VERSION + 1]] as $revision) {
            try { Task::fromArray(array_replace($task->toArray(), ['revision' => StrictJson::fromObject($revision)])); self::fail('Invalid revision'); } catch (DomainException) {}
        }
        try { $task->revision('visibility', Task::MAX_TIME + 1); self::fail('Invalid comparison time'); } catch (DomainException) {}
        try { $task->revision('visibility', 9); self::fail('Rollback'); } catch (DomainException) {}
        $maximum = Task::fromArray(array_replace($task->toArray(), ['revision' => StrictJson::fromObject(['incarnation' => str_repeat('a', 32), 'counter' => Task::MAX_VERSION])]));
        $inputs = $task->requestInput(\Fight\Common\Application\Mcp\Task\Service\TaskInputForms::batch(\Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired::fromRequests(['answer' => TaskFixture::form()]), 'caller', 100), 1, 10);
        $exhausted = Task::fromArray(array_replace($inputs->toArray(), ['revision' => $maximum->toArray()['revision']]));
        self::assertGreaterThan($exhausted->revision('visibility', 99)->get('value'), $exhausted->revision('visibility', 100)->get('value'));
        self::assertSame('working', $exhausted->view(100)['state']->get('status'));
        $this->expectException(DomainException::class);
        $maximum->transition('producer', 1, TaskFixture::completed(), null, 10);
    }
}
