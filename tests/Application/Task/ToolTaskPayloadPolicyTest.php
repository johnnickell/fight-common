<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Task;

use Fight\Common\Application\Task\Service\ToolTaskPayloadPolicy;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Task\Task;
use Fight\Common\Domain\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ToolTaskPayloadPolicy::class)]
final class ToolTaskPayloadPolicyTest extends UnitTestCase
{
    public function test_that_retained_tool_contract_preserves_business_errors_execution_errors_and_supported_input(): void
    {
        $policy = new ToolTaskPayloadPolicy(static fn () => false);
        foreach ([TaskState::create('working'), TaskFixture::input(), TaskFixture::completed(), TaskFixture::completed(true), TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])), TaskState::create('cancelled')] as $state) {
            $policy->validate(TaskFixture::definition(), $state);
        }
        self::assertFalse($policy->mayDisclose(Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 0)));
        $policy = new ToolTaskPayloadPolicy(static fn () => true, [123 => 'Safe failure']);
        $policy->validate(TaskFixture::definition(), TaskState::create('failed', error: StrictJson::fromObject(['code' => 123, 'message' => 'Safe failure'])));
        self::assertTrue($policy->mayDisclose(Task::create(TaskFixture::definition(), TaskState::create('working'), null, null, 0)));
    }

    public function test_that_unmapped_diagnostics_mismatched_results_and_unsupported_input_reject(): void
    {
        $policy = new ToolTaskPayloadPolicy(static fn () => true);
        $invalid = [
            TaskState::create('completed', result: StrictJson::fromObject()),
            TaskState::create('completed', result: StrictJson::fromObject(['content' => [], 'isError' => false])),
            TaskState::create('completed', result: StrictJson::fromObject(['content' => [], 'isError' => false, 'structuredContent' => (object) ['ok' => 'wrong']])),
            TaskState::create('completed', result: StrictJson::fromObject(['content' => [], 'isError' => true, 'extra' => true])),
            TaskState::create('completed', result: StrictJson::fromObject(['content' => [null], 'isError' => true])),
            TaskState::create('completed', result: StrictJson::fromObject(['content' => [(object) ['type' => 'text', 'text' => 'ok', 'extra' => true]], 'isError' => true])),
            TaskState::create('completed', result: StrictJson::fromObject(['content' => [(object) ['type' => 'image', 'text' => 'ok']], 'isError' => true])),
            TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'private exception'])),
            TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error', 'data' => (object) ['private' => 'secret']])),
            TaskState::create('input_required', input: StrictJson::fromObject(['a' => (object) ['type' => 'form']])),
            TaskState::create('input_required', input: StrictJson::fromObject(['a' => (object) ['type' => 'form', 'message' => 'ok', 'schema' => (object) ['type' => 'object', 'properties' => (object) ['nested' => (object) ['type' => 'object']]]]]))
        ];
        foreach ($invalid as $state) {
            try { $policy->validate(TaskFixture::definition(), $state); self::fail('Original contract violation must reject.'); } catch (DomainException) { self::assertTrue(true); }
        }
        try { $policy->validate(TaskFixture::definition(operation: StrictJson::fromObject(['kind' => 'unknown'])), TaskState::create('working')); self::fail('Unknown contract must reject.'); } catch (DomainException) { self::assertTrue(true); }
        foreach ([['private-code' => 'safe'], [1 => ''], [1 => "\xff"], [1 => str_repeat('x', 1025)]] as $errors) {
            try { new ToolTaskPayloadPolicy(static fn () => true, $errors); self::fail('Invalid public mapping must reject.'); } catch (DomainException) { self::assertTrue(true); }
        }
    }
}
