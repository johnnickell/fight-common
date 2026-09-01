<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Laravel;

use Fight\Common\Adapter\Messaging\Laravel\LaravelCommandBus;
use Fight\Common\Adapter\Messaging\Laravel\QueuedCommandMessage;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LaravelCommandBus::class)]
final class LaravelCommandBusTest extends UnitTestCase
{
    public function test_that_execute_submits_a_queue_job_for_the_created_command_message(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(static fn (mixed $job): bool => $job instanceof QueuedCommandMessage);

        (new LaravelCommandBus($dispatcher))->execute(new LaravelCommandBusCommand('command-86'));
    }

    public function test_that_dispatch_submits_a_queue_job_for_the_supplied_command_message(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(static fn (mixed $job): bool => $job instanceof QueuedCommandMessage);
        $message = CommandMessage::create(new LaravelCommandBusCommand('message-86'));

        (new LaravelCommandBus($dispatcher))->dispatch($message);
    }
}

final readonly class LaravelCommandBusCommand implements Command
{
    public function __construct(private string $reference)
    {
    }

    public static function fromArray(array $data): static
    {
        return new static($data['reference']);
    }

    public function toArray(): array
    {
        return ['reference' => $this->reference];
    }
}
