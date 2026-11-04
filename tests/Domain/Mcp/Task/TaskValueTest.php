<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Mcp\Task;

use Fight\Common\Domain\Collection\HashTable;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Command\CreateTask;
use Fight\Common\Domain\Mcp\Task\Command\RecordTaskState;
use Fight\Common\Domain\Mcp\Task\TaskDefinition;
use Fight\Common\Domain\Mcp\Task\TaskId;
use Fight\Common\Domain\Mcp\Task\TaskInputBatch;
use Fight\Common\Domain\Mcp\Task\TaskInputs;
use Fight\Common\Domain\Mcp\Task\TaskSelection;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Common\Domain\Value\ValueObject;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(TaskDefinition::class)]
#[CoversClass(TaskState::class)]
#[CoversClass(TaskInputBatch::class)]
#[CoversClass(TaskSelection::class)]
final class TaskValueTest extends UnitTestCase
{
    public function test_that_named_values_reconstruct_with_concrete_equality_hashing_and_structured_nested_json(): void
    {
        $values = [
            TaskFixture::definition(),
            TaskState::create('working', ''),
            self::batch(),
            TaskSelection::fromIds('scope', [TaskFixture::id()])
        ];
        foreach ($values as $value) {
            self::assertInstanceOf(ValueObject::class, $value);
            $equal = $value::fromString($value->toString());
            self::assertNotSame($value, $equal);
            self::assertTrue($value->equals($equal));
            self::assertTrue($equal->equals($value));
            self::assertSame($value->hashValue(), $equal->hashValue());
            self::assertSame($value->toString(), (string) $value);
            self::assertSame($value->toString(), $equal->toString());
            self::assertFalse($value->equals($value->toString()));
            self::assertFalse($value->equals(StrictJson::fromString($value->toString())));
            self::assertSame($value->toString(), json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
            $nested = json_decode(json_encode(['value' => $value], JSON_THROW_ON_ERROR), false, 96, JSON_THROW_ON_ERROR);
            self::assertIsObject($nested->value);
            self::assertTrue($value->equals($value::fromString(json_encode($nested->value, JSON_PRESERVE_ZERO_FRACTION))));
            $table = HashTable::of($value::class, 'string');
            $table->set($value, 'first');
            $table->set($equal, 'equal');
            self::assertCount(1, $table);
            self::assertSame('equal', $table->get($value));
            foreach ($values as $other) {
                if ($value::class !== $other::class) {
                    self::assertFalse($value->equals($other));
                }
            }
        }
    }

    public function test_that_definition_equality_includes_scope_producer_identity_and_exact_original_representation(): void
    {
        $operation = StrictJson::fromString('{"unknown":null,"empty":{},"list":[],"number":1.0}');
        $definition = TaskDefinition::create(TaskFixture::id(), 'scope', 'producer', $operation);
        $equal = TaskDefinition::create(TaskFixture::id(), 'scope', 'producer', StrictJson::fromString($operation->toString()));
        self::assertTrue($definition->equals($equal));
        foreach ([
            TaskDefinition::create(TaskFixture::id(str_repeat('f', 32)), 'scope', 'producer', $operation),
            TaskDefinition::create(TaskFixture::id(), 'other', 'producer', $operation),
            TaskDefinition::create(TaskFixture::id(), 'scope', 'other', $operation),
            TaskDefinition::create(TaskFixture::id(), 'scope', 'producer', StrictJson::fromString('{"empty":{},"unknown":null,"list":[],"number":1.0}')),
            TaskDefinition::create(TaskFixture::id(), 'scope', 'producer', $operation->with('number', 1)),
            TaskDefinition::create(TaskFixture::id(), 'scope', 'producer', $operation->with('empty', []))
        ] as $different) {
            self::assertFalse($definition->equals($different));
        }
        self::assertSame('{"id":"0123456789abcdef0123456789abcdef","scope":"scope","producer":"producer","operation":{"unknown":null,"empty":{},"list":[],"number":1.0}}', $definition->toString());
        self::assertTrue($definition->equals(TaskDefinition::fromString($definition->toString())));
        $command = new CreateTask($definition, TaskState::create('working'));
        $wire = json_encode($command->toArray(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $decoded = json_decode($wire, false, 96, JSON_THROW_ON_ERROR);
        self::assertSame(TaskFixture::ID, $decoded->definition->id);
        self::assertIsObject($decoded->definition->operation->empty);
        self::assertSame([], $decoded->definition->operation->list);
        self::assertSame(1.0, $decoded->definition->operation->number);
    }

    public function test_that_state_values_preserve_status_message_payload_kind_and_source_distinctions(): void
    {
        self::assertFalse(TaskState::create('working')->equals(TaskState::create('working', '')));
        self::assertFalse(TaskState::create('working')->equals(TaskState::create('cancelled')));
        $result = TaskState::create('completed', result: StrictJson::fromString('{"value":{},"number":1.0,"unknown":null,"list":[]}'));
        foreach (['{"value":[],"number":1.0,"unknown":null,"list":[]}', '{"value":{},"number":1,"unknown":null,"list":[]}', '{"value":{},"number":1.0,"list":[]}', '{"number":1.0,"value":{},"unknown":null,"list":[]}'] as $payload) {
            self::assertFalse($result->equals(TaskState::create('completed', result: StrictJson::fromString($payload))));
        }
        self::assertTrue($result->equals(TaskState::fromString($result->toString())));
        self::assertFalse(TaskFixture::completed()->equals(TaskFixture::completed(true)));
        $form = TaskState::create('input_required', input: StrictJson::fromString('{"request":{"type":"form"}}'));
        $confirmation = TaskState::create('input_required', input: StrictJson::fromString('{"request":{"type":"confirmation"}}'));
        self::assertFalse($form->equals($confirmation));
        $error = TaskState::create('failed', error: StrictJson::fromString('{"code":1,"message":"safe","data":null}'));
        self::assertFalse($error->equals(TaskState::create('failed', error: StrictJson::fromString('{"code":1,"message":"safe"}'))));
        self::assertTrue($error->equals(TaskState::fromString($error->toString())));
        self::assertSame('{"status":"working","message":null,"input":null,"result":null,"error":null}', TaskState::create('working')->toString());
        $command = new RecordTaskState('scope', TaskFixture::id(), 'producer', 1, $result);
        $wire = json_decode(json_encode($command->toArray(), JSON_PRESERVE_ZERO_FRACTION));
        self::assertSame(TaskFixture::ID, $wire->id);
        self::assertIsObject($wire->state->result->value);
        self::assertSame(1.0, $wire->state->result->number);
    }

    public function test_that_batch_values_describe_contracts_not_the_identity_or_consumption_of_a_retained_group(): void
    {
        $batch = self::batch();
        foreach ([
            TaskInputBatch::create('other', 100, false, $batch->data()->get('requests')),
            TaskInputBatch::create('caller', 101, false, $batch->data()->get('requests')),
            TaskInputBatch::create('caller', 100, true, $batch->data()->get('requests')),
            TaskInputBatch::create('caller', 100, false, StrictJson::fromString('{"answer":{"name":"answer","public":{},"contract":{"unknown":null}}}'))
        ] as $different) {
            self::assertFalse($batch->equals($different));
        }
        self::assertSame('{"caller":"caller","expires_at":100,"confirmation":false,"requests":{"answer":{"name":"answer","public":{},"contract":{}}}}', $batch->toString());
        $first = TaskInputs::empty('scope', TaskFixture::id())->issue($batch, 0);
        $second = $first->issue(self::batch(), 0);
        self::assertSame([TaskFixture::KEY_1, TaskFixture::KEY_2], array_keys($second->data()->get('groups')->properties()));
        self::assertTrue($batch->equals(self::batch()));
        self::assertFalse($first instanceof ValueObject);
    }

    public function test_that_selection_values_keep_exact_scope_and_supplied_order_and_reject_duplicate_equal_ids(): void
    {
        $first = TaskFixture::id();
        $second = TaskFixture::id(str_repeat('f', 32));
        $selection = TaskSelection::fromIds('scope', [$first, $second]);
        self::assertFalse($selection->equals(TaskSelection::fromIds('other', [$first, $second])));
        self::assertFalse($selection->equals(TaskSelection::fromIds('scope', [$second, $first])));
        self::assertFalse($selection->equals(TaskSelection::fromIds('scope', [$first])));
        self::assertSame('{"scope":"scope","ids":["0123456789abcdef0123456789abcdef","ffffffffffffffffffffffffffffffff"]}', $selection->toString());
        self::assertTrue($selection->equals(TaskSelection::fromString($selection->toString())));
        $this->expectException(DomainException::class);
        TaskSelection::fromIds('scope', [$first, TaskId::fromString($first->toString())]);
    }

    public function test_that_raw_strings_cannot_be_substituted_for_typed_selection_identities(): void
    {
        $this->expectException(DomainException::class);
        TaskSelection::fromIds('scope', [TaskFixture::ID]);
    }

    #[DataProvider('invalidRepresentations')]
    public function test_that_malformed_or_incoherent_value_reconstruction_rejects(string $type, string $value): void
    {
        $this->expectException(DomainException::class);
        $type::fromString($value);
    }

    public static function invalidRepresentations(): iterable
    {
        foreach ([TaskDefinition::class, TaskState::class, TaskInputBatch::class, TaskSelection::class] as $type) {
            foreach (['invalid', 'null', '[]', '{}'] as $value) {
                yield [$type, $value];
            }
        }
        yield [TaskDefinition::class, '{"id":null,"scope":"scope","producer":"producer","operation":{}}'];
        yield [TaskState::class, '{"status":null,"message":null,"input":null,"result":null,"error":null}'];
        yield [TaskState::class, '{"status":"completed","message":null,"input":null,"result":null,"error":null}'];
        yield [TaskInputBatch::class, '{"caller":null,"expires_at":100,"confirmation":false,"requests":{}}'];
        yield [TaskSelection::class, '{"scope":null,"ids":["0123456789abcdef0123456789abcdef"]}'];
        yield [TaskSelection::class, '{"scope":"scope","ids":null}'];
        yield [TaskSelection::class, '{"scope":"scope","ids":{"0":"0123456789abcdef0123456789abcdef"}}'];
        yield [TaskSelection::class, '{"scope":"scope","ids":[null]}'];
    }

    private static function batch(): TaskInputBatch
    {
        return TaskInputBatch::create('caller', 100, false, StrictJson::fromString('{"answer":{"name":"answer","public":{},"contract":{}}}'));
    }
}
