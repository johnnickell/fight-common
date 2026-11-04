<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Task;

use Fight\Common\Application\Mcp\Task\Projection\TaskProjection;
use Fight\Common\Application\Mcp\Task\Service\TaskInputForms;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TaskProjection::class)]
final class TaskProjectionTest extends UnitTestCase
{
    public function test_that_complete_query_fields_preserve_state_and_metadata_without_protected_evidence(): void
    {
        $task = Task::create(TaskFixture::definition(), TaskState::create('working', ''), 1000, 5, 10);
        $view = TaskProjection::fromTask($task, 'visibility', 10);

        self::assertSame([
            'id', 'version', 'state', 'created_at', 'updated_at', 'ttl', 'poll_interval', 'revision',
        ], array_keys($view->properties()));
        self::assertSame(TaskFixture::ID, $view->get('id'));
        self::assertSame(1, $view->get('version'));
        self::assertSame('', $view->get('state')->get('message'));
        self::assertSame(10, $view->get('created_at'));
        self::assertSame(10, $view->get('updated_at'));
        self::assertSame(1000, $view->get('ttl'));
        self::assertSame(5, $view->get('poll_interval'));
        self::assertSame(['scope', 'value'], array_keys($view->get('revision')->properties()));
        self::assertStringNotContainsString($task->incarnation(), $view->toString());
        foreach ([
            TaskFixture::completed(true),
            TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])),
            TaskState::create('cancelled'),
        ] as $state) {
            $next = $task->transition('producer', 1, $state, null, 11);
            $projection = TaskProjection::fromTask($next, 'visibility', 11);
            self::assertSame($state->data()->toString(), $projection->get('state')->toString());
            self::assertNull($projection->get('poll_interval'));
        }
    }

    public function test_that_fixed_revision_vectors_preserve_exact_hmac_framing_width_and_carry(): void
    {
        $task = Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 10);
        // Independently computed HMAC/addition vectors, not a second copy of the production encoder.
        foreach ([
            1 => 'v1:006467bccf2a2e3616d36db48b104ae5eb',
            2 => 'v1:006467bccf2a2e3616d36db48b104ae60b',
            Task::MAX_VERSION => 'v1:006467bccf2a2e3616d36db49b104ae5ab',
        ] as $counter => $expected) {
            $restored = Task::fromArray(array_replace($task->toArray(), [
                'revision' => StrictJson::fromObject(['incarnation' => str_repeat('a', 32), 'counter' => $counter]),
            ]));
            $revision = TaskProjection::fromTask($restored, 'visibility', 10)->get('revision');
            self::assertSame('d8f824b15b7cfb2b31113cd946d955ebb14860f2b5e3e16437af2ff16ce0fb10', $revision->get('scope'));
            self::assertSame($expected, $revision->get('value'));
        }
    }

    public function test_that_simultaneous_expiry_changes_one_order_slot_and_preserves_complete_input_messages(): void
    {
        $task = Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 0);
        $forms = McpInputRequired::fromRequests(['answer' => TaskFixture::form()]);
        $task = $task->requestInput(TaskInputForms::batch($forms, 'caller', 100), 1, 0);
        $task = $task->requestInput(TaskInputForms::batch($forms, 'caller', 100), 2, 0);
        $task = $task->transition('producer', 3, TaskState::create('input_required', 'Wait', $task->stateAt(0)->data()->get('input')), null, 0);

        $before = TaskProjection::fromTask($task, 'visibility', 99);
        $expired = TaskProjection::fromTask($task, 'visibility', 100);

        self::assertCount(2, $before->get('state')->get('input')->properties());
        self::assertSame('Wait', $before->get('state')->get('message'));
        self::assertSame('working', $expired->get('state')->get('status'));
        self::assertNull($expired->get('state')->get('message'));
        self::assertNull($expired->get('state')->get('input'));
        self::assertSame($before->get('revision')->get('scope'), $expired->get('revision')->get('scope'));
        self::assertGreaterThan($before->get('revision')->get('value'), $expired->get('revision')->get('value'));
        self::assertSame(1, $task->publicOrder(100) - $task->publicOrder(99));
        self::assertSame($expired->toString(), TaskProjection::fromTask(Task::fromArray($task->toArray()), 'visibility', 100)->toString());
    }

    public function test_that_invalid_visibility_context_never_fabricates_comparable_state(): void
    {
        $task = Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 0);
        foreach (['', str_repeat('v', 97), 'untrusted context'] as $context) {
            try {
                TaskProjection::fromTask($task, $context, 0);
                self::fail('Invalid comparison context');
            } catch (DomainException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
