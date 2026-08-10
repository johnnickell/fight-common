<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Messaging\Event;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Command\Command;

/**
 * Class CommandFailedEvent
 */
final readonly class CommandFailedEvent implements Event
{
    /**
     * Constructs CommandFailedEvent
     */
    public function __construct(private readonly Command $command, private readonly string $errorMessage)
    {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        $keys = ['command_class', 'command_data', 'error_message'];

        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                $message = sprintf('Missing required key "%s" in data array', $key);
                throw new DomainException($message);
            }
        }

        $commandClass = $data['command_class'];
        $command = $commandClass::fromArray($data['command_data']);
        $errorMessage = $data['error_message'];

        return new static($command, $errorMessage);
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'command_class' => $this->command::class,
            'command_data'  => $this->command->toArray(),
            'error_message' => $this->errorMessage
        ];
    }

    /**
     * Retrieves the command that failed
     */
    public function getCommand(): Command
    {
        return $this->command;
    }

    /**
     * Retrieves the error message
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }
}
