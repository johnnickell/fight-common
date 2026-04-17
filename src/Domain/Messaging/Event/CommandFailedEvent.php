<?php

declare(strict_types=1);

namespace Fight\Common\Application\Messaging\Event;

/**
 * Class CommandFailedEvent
 */
class CommandFailedEvent implements Event
{
    /**
     * Constructs CommandFailedEvent
     */
    public function __construct(private readonly Command $command, private readonly string $errorMessage)
    {
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
