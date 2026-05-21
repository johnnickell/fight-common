<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Command\Sync;

use Fight\Common\Adapter\Messaging\Command\Sync\Routing\CommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RoutingCommandBus::class)]
class RoutingCommandBusTest extends UnitTestCase
{
    public function test_that_execute_wraps_command_and_dispatches_to_handler(): void
    {
        /** @var MockInterface|CommandHandler $handler */
        $handler = $this->mock(CommandHandler::class);
        $handler->shouldReceive('handle')
            ->once()
            ->withArgs(function (CommandMessage $msg): bool {
                return $msg->payload() instanceof SampleRoutingCommand;
            });

        /** @var MockInterface|CommandRouter $router */
        $router = $this->mock(CommandRouter::class);
        $router->shouldReceive('match')->andReturn($handler);

        $bus = new RoutingCommandBus($router);
        $bus->execute(new SampleRoutingCommand());
    }

    public function test_that_dispatch_routes_message_to_matched_handler(): void
    {
        $command = new SampleRoutingCommand();
        $message = CommandMessage::create($command);

        /** @var MockInterface|CommandHandler $handler */
        $handler = $this->mock(CommandHandler::class);
        $handler->shouldReceive('handle')->once()->with($message);

        /** @var MockInterface|CommandRouter $router */
        $router = $this->mock(CommandRouter::class);
        $router->shouldReceive('match')->with($command)->andReturn($handler);

        $bus = new RoutingCommandBus($router);
        $bus->dispatch($message);
    }
}

class SampleRoutingCommand implements Command
{
    public static function fromArray(array $data): static
    {
        return new static();
    }

    public function toArray(): array
    {
        return [];
    }
}
