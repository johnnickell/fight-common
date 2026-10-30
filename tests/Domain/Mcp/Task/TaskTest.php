<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Mcp\Task;

use Fight\Common\Application\Mcp\Task\Service\TaskInputForms;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Command\CreateTask;
use Fight\Common\Domain\Mcp\Task\Command\RecordTaskState;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\Query\GetTask;
use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskDefinition;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Task::class)]
#[CoversClass(TaskDefinition::class)]
#[CoversClass(TaskState::class)]
#[CoversClass(CreateTask::class)]
#[CoversClass(RecordTaskState::class)]
#[CoversClass(GetTask::class)]
#[CoversClass(TaskChanged::class)]
final class TaskTest extends UnitTestCase
{
    public function test_that_original_context_messages_and_coherent_states_round_trip_without_mutable_aliases(): void
    {
        $definition = TaskFixture::definition();
        self::assertSame(32, strlen(TaskDefinition::newId()));
        self::assertSame('producer', $definition->producer());
        self::assertSame('example.operation', $definition->operation()->get('name'));
        $command = new CreateTask($definition, TaskState::create('working', 'Preparing'), 1000, 1);
        self::assertEquals($command->toArray(), CreateTask::fromArray($command->toArray())->toArray());
        self::assertSame($definition, $command->definition());
        self::assertSame('working', $command->state()->status());
        $task = $command->at(100);
        self::assertEquals($task->toArray(), Task::fromArray($task->toArray())->toArray());
        self::assertSame($definition, $task->definition());
        self::assertSame(1, $task->version());
        self::assertTrue($task->availableAt(1099)); self::assertFalse($task->availableAt(1100));
        self::assertTrue($task->repeatsCreation($command->at(500)));
        self::assertTrue(Task::create($definition, TaskState::create('working'), null, null, 0)->availableAt(Task::MAX_TIME));
        $query = new GetTask('scope', TaskFixture::ID);
        self::assertSame($query->toArray(), GetTask::fromArray($query->toArray())->toArray());
        self::assertSame('scope', $query->scope()); self::assertSame(TaskFixture::ID, $query->id());
        foreach ([TaskFixture::completed(), TaskFixture::completed(true), TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])), TaskState::create('cancelled')] as $state) {
            self::assertEquals($state->toArray(), TaskState::fromArray($state->toArray())->toArray());
            $update = new RecordTaskState('scope', TaskFixture::ID, 'producer', 1, $state, 5);
            self::assertEquals($update->toArray(), RecordTaskState::fromArray($update->toArray())->toArray());
            self::assertSame('scope', $update->scope()); self::assertSame(TaskFixture::ID, $update->id());
            self::assertSame('producer', $update->producer()); self::assertSame($state, $update->state());
            $next = $update->applyTo($task, 50);
            self::assertSame(2, $next->version()); self::assertSame($state, $next->state());
            self::assertSame(100, $next->view()['updated_at']);
            self::assertNull($update->applyTo($next, 1000));
            $fact = TaskChanged::fromTask($next);
            self::assertSame(['scope' => 'scope', 'id' => TaskFixture::ID, 'version' => 2], $fact->toArray());
            self::assertSame($fact->toArray(), TaskChanged::fromArray($fact->toArray())->toArray());
            self::assertArrayNotHasKey('creation_digest', $next->view());
        }
        $input = $task->requestInput(\Fight\Common\Application\Mcp\Task\Service\TaskInputForms::batch(\Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired::fromRequests(['form' => TaskFixture::form()]), 'caller', 1000), 1, 200);
        $resumed = $input->retireInput(TaskFixture::KEY_1, 2, 201);
        self::assertSame('working', $resumed->state()->status());
    }

    public function test_that_outstanding_input_projection_preserves_the_admitted_message_until_all_requests_expire(): void
    {
        foreach (['Waiting for both authorized answers', '', null] as $message) {
            $task = Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 0);
            $forms = McpInputRequired::fromRequests(['answer' => TaskFixture::form()]);
            $task = $task->requestInput(TaskInputForms::batch($forms, 'caller', 100), 1, 0);
            $task = $task->requestInput(TaskInputForms::batch($forms, 'caller', 200), 2, 0);
            $requests = $task->view(0)['state']->get('input');
            $task = $task->transition('producer', 3, TaskState::create('input_required', $message, $requests), null, 0);
            $before = $task->toArray();
            foreach ([0, 99, 100, 199] as $now) {
                $view = $task->view($now);
                self::assertSame($message, $view['state']->get('message'));
                self::assertSame($now < 100 ? [TaskFixture::KEY_1, TaskFixture::KEY_2] : [TaskFixture::KEY_2], array_keys($view['state']->get('input')->properties()));
                self::assertSame(4, $view['version']);
                self::assertEquals($before, $task->toArray());
            }
            self::assertSame('working', $task->view(200)['state']->get('status'));
            self::assertNull($task->view(200)['state']->get('message'));
            self::assertNull($task->view(200)['state']->get('input'));
            self::assertSame($message, $task->view()['state']->get('message'));
            self::assertEquals($before, $task->toArray());
        }
    }

    #[DataProvider('invalidStates')]
    public function test_that_state_owned_payload_invariants_reject_before_persistence(
        string $status,
        ?string $message = null,
        ?StrictJson $input = null,
        ?StrictJson $result = null,
        ?StrictJson $error = null
    ): void {
        $this->expectException(DomainException::class);
        TaskState::create($status, $message, $input, $result, $error);
    }

    public static function invalidStates(): iterable
    {
        yield ['status' => 'unknown'];
        yield ['status' => 'working', 'message' => str_repeat('x', 1025)];
        yield ['status' => 'working', 'message' => "\xff"];
        yield ['status' => 'input_required'];
        yield ['status' => 'working', 'input' => StrictJson::fromObject()];
        yield ['status' => 'input_required', 'input' => StrictJson::fromObject()];
        yield ['status' => 'input_required', 'input' => StrictJson::fromData([])];
        yield ['status' => 'input_required', 'input' => StrictJson::fromObject(array_fill_keys(range('a', 'q'), (object) ['type' => 'form']))];
        yield ['status' => 'input_required', 'input' => StrictJson::fromObject(['bad key' => (object) ['type' => 'form']])];
        yield ['status' => 'input_required', 'input' => StrictJson::fromObject(['a' => null])];
        yield ['status' => 'input_required', 'input' => StrictJson::fromObject(['a' => (object) ['type' => 'url']])];
        yield ['status' => 'completed'];
        yield ['status' => 'completed', 'result' => StrictJson::fromData([])];
        yield ['status' => 'working', 'result' => StrictJson::fromObject()];
        yield ['status' => 'failed'];
        yield ['status' => 'working', 'error' => StrictJson::fromObject()];
        foreach ([[], ['code' => '1', 'message' => 'safe'], ['code' => -2147483649, 'message' => 'safe'], ['code' => 2147483648, 'message' => 'safe'], ['code' => 1, 'message' => ''], ['code' => 1, 'message' => str_repeat('x', 1025)], ['code' => 1, 'message' => 'safe', 'extra' => true]] as $error) {
            yield ['status' => 'failed', 'error' => StrictJson::fromObject($error)];
        }
        yield ['status' => 'completed', 'result' => StrictJson::fromObject(['body' => str_repeat('x', 32768)])];
    }

    public function test_that_encoded_limits_include_escaping_multibyte_bytes_and_exact_boundary(): void
    {
        $empty = TaskState::create('completed', result: StrictJson::fromObject(['body' => '']));
        $capacity = TaskState::MAX_BYTES - strlen($empty->data()->toString());
        $state = TaskState::create('completed', result: StrictJson::fromObject(['body' => str_repeat('x', $capacity)]));
        self::assertSame(TaskState::MAX_BYTES, strlen($state->data()->toString()));
        self::assertSame(1024, strlen(TaskState::create('working', str_repeat('é', 512))->data()->get('message')));
        $map = array_fill_keys(range('a', 'p'), (object) ['type' => 'confirmation']);
        self::assertCount(16, TaskState::create('input_required', input: StrictJson::fromObject($map))->data()->get('input')->properties());
        self::assertNotNull(TaskState::create('failed', error: StrictJson::fromObject(['code' => -2147483648, 'message' => 'safe', 'data' => (object) []])));
        self::assertNotNull(TaskState::create('failed', error: StrictJson::fromObject(['code' => 2147483647, 'message' => 'safe'])));
        foreach ([str_repeat('"', $capacity), str_repeat('é', $capacity), str_repeat('x', $capacity + 1)] as $body) {
            try { TaskState::create('completed', result: StrictJson::fromObject(['body' => $body])); self::fail('Encoded limit must reject.'); } catch (DomainException) { self::assertTrue(true); }
        }
    }

    public function test_that_original_context_depth_is_owned_even_when_the_supplied_json_has_a_larger_budget(): void
    {
        $nested = 'leaf';
        for ($depth = 0; $depth < 63; ++$depth) { $nested = (object) ['node' => $nested]; }
        $exact = StrictJson::fromObject(['retained' => $nested], 128);
        self::assertSame($exact->toString(), TaskFixture::definition(operation: $exact)->operation()->toString());
        $over = StrictJson::fromObject(['retained' => (object) ['node' => $nested]], 128);
        $this->expectException(DomainException::class);
        TaskFixture::definition(operation: $over);
    }

    public function test_that_incomplete_reconstruction_rejects_without_runtime_warnings(): void
    {
        foreach ([fn () => TaskDefinition::fromArray([]), fn () => TaskState::fromArray([]), fn () => Task::fromArray([])] as $operation) {
            try { $operation(); self::fail('Incomplete representations must reject safely.'); }
            catch (DomainException) { self::assertTrue(true); }
        }
    }

    public function test_that_definition_metadata_and_version_bounds_reject_without_coercing_or_reopening(): void
    {
        $task = Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 10);
        $invalid = [
            fn () => TaskDefinition::identity('guessed'), fn () => TaskDefinition::reference(''),
            fn () => TaskDefinition::create(TaskFixture::ID, 'scope', 'producer', StrictJson::fromData([])),
            fn () => TaskDefinition::create(TaskFixture::ID, 'scope', 'producer', StrictJson::fromObject(['x' => str_repeat('x', 8192)])),
            fn () => Task::create(TaskFixture::definition(), TaskState::create('cancelled'), null, null, 0),
            fn () => Task::create(TaskFixture::definition(), TaskState::create('working'), 1, null, Task::MAX_TIME),
            fn () => Task::timestamp(-1), fn () => Task::timestamp(Task::MAX_TIME + 1),
            fn () => Task::interval(0), fn () => Task::interval(2147483648),
            fn () => new RecordTaskState('scope', TaskFixture::ID, 'producer', 0, TaskState::create('working')),
            fn () => new RecordTaskState('scope', TaskFixture::ID, 'producer', Task::MAX_VERSION + 1, TaskState::create('working')),
            fn () => TaskChanged::fromArray(['scope' => 'scope', 'id' => TaskFixture::ID, 'version' => 0])
        ];
        foreach (['schema' => 2, 'version' => 0, 'updated_at' => 9, 'creation_digest' => 'invalid', 'last_digest' => 'invalid'] as $key => $value) {
            $invalid[] = fn () => Task::fromArray(array_replace($task->toArray(), [$key => $value]));
        }
        foreach ($invalid as $operation) { try { $operation(); self::fail('Invalid metadata must reject.'); } catch (DomainException) { self::assertTrue(true); } }
        self::assertSame(2147483647, Task::interval(2147483647)); self::assertSame(Task::MAX_TIME, Task::timestamp(Task::MAX_TIME));
        $terminal = $task->transition('producer', 1, TaskFixture::completed(), null, 20);
        $maximum = Task::fromArray(array_replace($task->toArray(), ['version' => Task::MAX_VERSION, 'last_digest' => str_repeat('a', 64)]));
        foreach ([fn () => $task->transition('other', 1, TaskFixture::completed(), null, 20), fn () => $task->transition('producer', 0, TaskFixture::completed(), null, 20), fn () => $task->transition('producer', 2, TaskFixture::completed(), null, 20), fn () => $terminal->transition('producer', 2, TaskState::create('working'), null, 21), fn () => $maximum->transition('producer', Task::MAX_VERSION, TaskFixture::completed(), null, 20)] as $operation) {
            try { $operation(); self::fail('Conflicting transition must reject.'); } catch (DomainException) { self::assertTrue(true); }
        }
    }
}
