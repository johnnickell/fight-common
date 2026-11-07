<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Task;

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskSchema;
use Fight\Common\Application\Mcp\Task\McpTasks;
use Fight\Common\Application\Mcp\Task\Projection\TaskProjection;
use Fight\Common\Application\Mcp\Task\QueryHandler\GetTaskHandler;
use Fight\Common\Application\Mcp\Task\Service\TaskInputForms;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskInputs;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\Fixture\Mcp\TaskEndpoint;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TaskProjection::class)]
#[CoversClass(Task::class)]
#[CoversClass(TaskInputs::class)]
#[CoversClass(GetTaskHandler::class)]
#[CoversClass(McpTasks::class)]
final class TaskRevisionTest extends UnitTestCase
{
    public function test_that_equal_timestamps_private_mutations_and_reconstruction_do_not_leak_mutation_gaps(): void
    {
        $task = Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 10);
        $first = TaskProjection::fromTask($task, 'visibility', 10)->get('revision');

        $cancel = $task->requestCancellation(10);

        self::assertSame(2, $cancel->version());
        self::assertSame($first->toString(), TaskProjection::fromTask($cancel, 'visibility', 10)->get('revision')->toString());

        $next = $cancel->transition('producer', 2, TaskFixture::completed(true), null, 10);

        self::assertGreaterThan($first->get('value'), TaskProjection::fromTask($next, 'visibility', 10)->get('revision')->get('value'));
        self::assertSame(2, $next->toArray()['revision']->get('counter'));
        self::assertSame($first->get('scope'), TaskProjection::fromTask($next, 'visibility', 10)->get('revision')->get('scope'));
        self::assertSame(
            TaskProjection::fromTask($next, 'visibility', 10)->get('revision')->toString(),
            TaskProjection::fromTask(Task::fromArray($next->toArray()), 'visibility', 10)->get('revision')->toString(),
        );
        self::assertNotSame($first->get('scope'), TaskProjection::fromTask($next, 'different-visibility', 10)->get('revision')->get('scope'));
        self::assertNotSame(
            $first->get('scope'),
            TaskProjection::fromTask(
                Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 10),
                'visibility',
                10,
            )->get('revision')->get('scope'),
        );
    }

    public function test_that_input_expiry_and_later_mutations_never_reuse_an_equal_revision_for_incompatible_state(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new DbalTaskSchema())->install($connection);
        $fixture = new TaskFixture($connection);
        $fixture->now = 10;
        $fixture->create();
        $fixture->issue(expiresAt: 100);
        $fixture->issue(expectedVersion: 2, expiresAt: 200);
        $old = $fixture->get();

        $fixture->now = 100;
        $partial = $fixture->get();

        self::assertCount(1, $partial->get('state')->get('input')->properties());
        self::assertGreaterThan($old->get('revision')->get('value'), $partial->get('revision')->get('value'));

        $fixture->cancel();
        $after = $fixture->get();

        self::assertGreaterThan($partial->get('revision')->get('value'), $after->get('revision')->get('value'));

        $fixture->now = 200;
        $expired = $fixture->get();

        self::assertSame('working', $expired->get('state')->get('status'));
        self::assertGreaterThan($after->get('revision')->get('value'), $expired->get('revision')->get('value'));

        $fixture->record(TaskFixture::completed(), expectedVersion: 4);

        self::assertGreaterThan($expired->get('revision')->get('value'), $fixture->get()->get('revision')->get('value'));
        self::assertSame(3, $old->get('version'));
    }

    public function test_that_a_later_committed_write_cannot_reuse_a_revision_observed_across_input_expiry_on_another_clock(): void
    {
        $task = Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 0);
        $input = McpInputRequired::fromRequests(['answer' => TaskFixture::form()]);
        $task = $task->requestInput(TaskInputForms::batch($input, 'caller', 100), 1, 0);
        $expired = TaskProjection::fromTask($task, 'visibility', 100)->get('revision');
        self::assertSame('working', $task->stateAt(100)->data()->get('status'));

        // Independent clocks can straddle a deadline by 1 ms without either process moving backwards.
        $later = $task->transition(
            'producer',
            2,
            TaskState::create('input_required', 'New producer state', $task->stateAt(99)->data()->get('input')),
            null,
            99,
        );

        self::assertSame('input_required', $later->stateAt(99)->data()->get('status'));
        $newer = TaskProjection::fromTask($later, 'visibility', 99)->get('revision');
        self::assertSame($expired->get('scope'), $newer->get('scope'));
        self::assertGreaterThan($expired->get('value'), $newer->get('value'));
    }

    public function test_that_final_current_read_checks_follow_projection_context_and_clock_callbacks(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new DbalTaskSchema())->install($connection);
        $fixture = new TaskFixture($connection);
        $fixture->create(ttl: 100);
        $endpoint = new TaskEndpoint($fixture);

        $fixture->onComparison = function () use ($fixture): void {
            $fixture->readAllowed = false;
        };

        self::assertNull($fixture->get());
        self::assertNull($endpoint->taskCapability->current(TaskFixture::ID));

        $fixture->readAllowed = true;
        $fixture->onComparison = function () use ($fixture): void {
            $fixture->discloseAllowed = false;
        };

        self::assertNull($fixture->get());

        $fixture->discloseAllowed = true;
        $fixture->onComparison = function () use ($fixture): void {
            $fixture->now += 100;
        };

        self::assertNull($fixture->get());
    }

    public function test_that_corrupt_exhausted_and_clock_rollback_revisions_fail_without_fabricating_comparison(): void
    {
        $task = Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 10);
        foreach ([
            ['incarnation' => 'bad', 'counter' => 1],
            ['incarnation' => str_repeat('a', 32), 'counter' => 0],
            ['incarnation' => str_repeat('a', 32), 'counter' => Task::MAX_VERSION + 1],
        ] as $revision) {
            try {
                Task::fromArray(array_replace($task->toArray(), ['revision' => StrictJson::fromObject($revision)]));
                self::fail('Invalid revision');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }

        try {
            TaskProjection::fromTask($task, 'visibility', Task::MAX_TIME + 1);
            self::fail('Invalid comparison time');
        } catch (DomainException) {
            self::addToAssertionCount(1);
        }

        try {
            TaskProjection::fromTask($task, 'visibility', 9);
            self::fail('Rollback');
        } catch (DomainException) {
            self::addToAssertionCount(1);
        }

        $maximum = Task::fromArray(array_replace($task->toArray(), [
            'revision' => StrictJson::fromObject([
                'incarnation' => str_repeat('a', 32),
                'counter' => Task::MAX_VERSION,
            ]),
        ]));
        $input = McpInputRequired::fromRequests(['answer' => TaskFixture::form()]);
        $inputs = $task->requestInput(TaskInputForms::batch($input, 'caller', 100), 1, 10);
        $exhausted = Task::fromArray(array_replace($inputs->toArray(), ['revision' => $maximum->toArray()['revision']]));

        self::assertGreaterThan(
            TaskProjection::fromTask($exhausted, 'visibility', 99)->get('revision')->get('value'),
            TaskProjection::fromTask($exhausted, 'visibility', 100)->get('revision')->get('value'),
        );
        self::assertSame('working', $exhausted->stateAt(100)->data()->get('status'));

        $this->expectException(DomainException::class);
        $maximum->transition('producer', 1, TaskFixture::completed(), null, 10);
    }
}
