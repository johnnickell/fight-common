<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Messaging\Event;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Event\CommandFailedEvent;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CommandFailedEvent::class)]
class CommandFailedEventTest extends UnitTestCase
{
    // -------------------------------------------------------------------------
    // fromArray / toArray
    // -------------------------------------------------------------------------

    public function test_that_from_array_creates_a_correct_instance(): void
    {
        $event = CommandFailedEvent::fromArray([
            'command_class' => SampleFailedCommand::class,
            'command_data'  => ['value' => 'test'],
            'error_message' => 'Something went wrong',
        ]);

        self::assertSame('Something went wrong', $event->getErrorMessage());
        self::assertInstanceOf(SampleFailedCommand::class, $event->getCommand());
    }

    public function test_that_to_array_returns_expected_structure(): void
    {
        $command = new SampleFailedCommand('my-value');
        $event = new CommandFailedEvent($command, 'Failure reason');

        $array = $event->toArray();

        self::assertSame(SampleFailedCommand::class, $array['command_class']);
        self::assertSame(['value' => 'my-value'], $array['command_data']);
        self::assertSame('Failure reason', $array['error_message']);
    }

    public function test_that_from_array_round_trips_correctly_from_to_array(): void
    {
        $original = new CommandFailedEvent(new SampleFailedCommand('abc'), 'error');

        $restored = CommandFailedEvent::fromArray($original->toArray());

        self::assertSame($original->getErrorMessage(), $restored->getErrorMessage());
        self::assertSame($original->getCommand()->toArray(), $restored->getCommand()->toArray());
    }

    // -------------------------------------------------------------------------
    // fromArray error cases
    // -------------------------------------------------------------------------

    public function test_that_from_array_throws_for_missing_command_class_key(): void
    {
        $this->expectException(DomainException::class);
        CommandFailedEvent::fromArray([
            'command_data'  => [],
            'error_message' => 'error',
        ]);
    }

    public function test_that_from_array_throws_for_missing_command_data_key(): void
    {
        $this->expectException(DomainException::class);
        CommandFailedEvent::fromArray([
            'command_class' => SampleFailedCommand::class,
            'error_message' => 'error',
        ]);
    }

    public function test_that_from_array_throws_for_missing_error_message_key(): void
    {
        $this->expectException(DomainException::class);
        CommandFailedEvent::fromArray([
            'command_class' => SampleFailedCommand::class,
            'command_data'  => [],
        ]);
    }
}

class SampleFailedCommand implements Command
{
    public function __construct(private readonly string $value = '') {}

    public static function fromArray(array $data): static
    {
        return new static($data['value'] ?? '');
    }

    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}
