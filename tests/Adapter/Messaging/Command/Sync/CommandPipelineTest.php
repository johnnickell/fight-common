<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Command\Sync;

use Fight\Common\Adapter\Messaging\Command\Sync\CommandPipeline;
use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CommandPipeline::class)]
class CommandPipelineTest extends UnitTestCase
{
    public function test_that_execute_wraps_command_and_dispatches(): void
    {
        /** @var MockInterface|SynchronousCommandBus $bus */
        $bus = $this->mock(SynchronousCommandBus::class);
        $bus->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn(CommandMessage $msg): bool => $msg->payload() instanceof SamplePipelineCommand);

        $pipeline = new CommandPipeline($bus);
        $pipeline->execute(new SamplePipelineCommand());
    }

    public function test_that_dispatch_pipes_message_to_inner_bus(): void
    {
        /** @var MockInterface|SynchronousCommandBus $bus */
        $bus = $this->mock(SynchronousCommandBus::class);
        $bus->shouldReceive('dispatch')->once();

        $pipeline = new CommandPipeline($bus);
        $pipeline->dispatch(CommandMessage::create(new SamplePipelineCommand()));
    }

    public function test_that_process_delegates_to_inner_bus(): void
    {
        /** @var MockInterface|SynchronousCommandBus $bus */
        $bus = $this->mock(SynchronousCommandBus::class);
        $bus->shouldReceive('dispatch')->once();

        $pipeline = new CommandPipeline($bus);
        $message = CommandMessage::create(new SamplePipelineCommand());
        $pipeline->process($message, function (): void {});
    }

    public function test_that_add_filter_is_called_before_inner_bus(): void
    {
        $calls = [];

        /** @var MockInterface|SynchronousCommandBus $bus */
        $bus = $this->mock(SynchronousCommandBus::class);
        $bus->shouldReceive('dispatch')->andReturnUsing(function () use (&$calls): void {
            $calls[] = 'bus';
        });

        $filter = new class ($calls) implements CommandFilter {
            public function __construct(private array &$calls) {}

            public function process(CommandMessage $commandMessage, callable $next): void
            {
                $this->calls[] = 'filter';
                $next($commandMessage);
            }
        };

        $pipeline = new CommandPipeline($bus);
        $pipeline->addFilter($filter);
        $pipeline->dispatch(CommandMessage::create(new SamplePipelineCommand()));

        self::assertSame(['filter', 'bus'], $calls);
    }

    public function test_that_multiple_filters_execute_in_lifo_order(): void
    {
        $calls = [];

        /** @var MockInterface|SynchronousCommandBus $bus */
        $bus = $this->mock(SynchronousCommandBus::class);
        $bus->shouldReceive('dispatch')->andReturnUsing(function () use (&$calls): void {
            $calls[] = 'bus';
        });

        $makeFilter = function (string $name) use (&$calls): CommandFilter {
            return new class ($name, $calls) implements CommandFilter {
                public function __construct(private readonly string $name, private array &$calls) {}

                public function process(CommandMessage $commandMessage, callable $next): void
                {
                    $this->calls[] = $this->name;
                    $next($commandMessage);
                }
            };
        };

        $pipeline = new CommandPipeline($bus);
        $pipeline->addFilter($makeFilter('first'));
        $pipeline->addFilter($makeFilter('second'));
        $pipeline->dispatch(CommandMessage::create(new SamplePipelineCommand()));

        self::assertSame(['second', 'first', 'bus'], $calls);
    }
}

class SamplePipelineCommand implements Command
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
