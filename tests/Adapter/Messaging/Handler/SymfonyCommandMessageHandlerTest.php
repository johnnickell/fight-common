<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Handler;

use Fight\Common\Adapter\Messaging\Handler\SymfonyCommandMessageHandler;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SymfonyCommandMessageHandler::class)]
class SymfonyCommandMessageHandlerTest extends UnitTestCase
{
    public function test_that_invoke_dispatches_command_message(): void
    {
        $commandMessage = CommandMessage::create(new SampleCommand('test'));

        $commandBus = $this->mock(SynchronousCommandBus::class);
        $commandBus->shouldReceive('dispatch')->once()->with($commandMessage);

        $handler = new SymfonyCommandMessageHandler($commandBus);

        $handler->__invoke($commandMessage);
    }
}

class SampleCommand implements Command
{
    public function __construct(private readonly string $value = '')
    {
    }

    public static function fromArray(array $data): static
    {
        return new static($data['value'] ?? '');
    }

    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}
