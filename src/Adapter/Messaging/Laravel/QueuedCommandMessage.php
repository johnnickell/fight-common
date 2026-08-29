<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Laravel;

use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Class QueuedCommandMessage
 *
 * The job is submitted only after the surrounding Laravel database transaction
 * commits. It is not an atomic outbox: consumers must remain idempotent.
 */
final class QueuedCommandMessage implements ShouldQueue
{
    use Queueable;

    /** @var array<string, mixed> */
    private array $message;

    /**
     * Constructs QueuedCommandMessage
     */
    public function __construct(CommandMessage $message)
    {
        $this->message = $message->arraySerialize();
        $this->afterCommit();
    }

    /**
     * Reconstitutes and synchronously handles the queued command envelope
     */
    public function handle(CommandMessageHandler $handler): void
    {
        $handler(CommandMessage::arrayDeserialize($this->message));
    }
}
