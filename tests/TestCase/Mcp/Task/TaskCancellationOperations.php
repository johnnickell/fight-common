<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp\Task;

use Fight\Common\Application\Mcp\Task\Service\TaskInputForms;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Domain\Mcp\Task\Command\AcquireTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\RecordTaskState;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\SupplyTaskInput;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Value\Basic\StrictJson;

final class TaskCancellationOperations
{
    public static function invoke(TaskFixture $fixture, string $operation, int $version): void
    {
        $id = TaskFixture::id();
        match ($operation) {
            'cancel' => $fixture->requestTaskCancellationHandler->handle(CommandMessage::create(new RequestTaskCancellation('scope', $id, 'caller'))),
            'supply' => $fixture->supplyTaskInputHandler->handle(CommandMessage::create(new SupplyTaskInput('scope', $id, 'caller', StrictJson::fromObject([
                TaskFixture::KEY_1 => ['action' => 'accept', 'content' => (object) ['answer' => 'accepted']]
            ])))),
            'acquire' => $fixture->acquireTaskInputHandler->handle(CommandMessage::create(new AcquireTaskInput('scope', $id, 'caller', TaskFixture::KEY_1))),
            'issue' => $fixture->requestTaskInputHandler->handle(CommandMessage::create(new RequestTaskInput(
                'scope', $id, 'producer', $version,
                TaskInputForms::batch(McpInputRequired::fromRequests(['form-0' => TaskFixture::form()]), 'caller', $fixture->now + 1000)
            ))),
            'completed', 'failed', 'cancelled' => $fixture->recordTaskStateHandler->handle(CommandMessage::create(new RecordTaskState(
                'scope', $id, 'producer', $version, self::terminalState($operation)
            )))
        };
    }

    private static function terminalState(string $operation): TaskState
    {
        return match ($operation) {
            'completed' => TaskFixture::completed(),
            'failed' => TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])),
            'cancelled' => TaskState::create('cancelled')
        };
    }
}
