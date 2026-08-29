<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Laravel;

use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Class QueuedEventMessage
 *
 * Queues complete event occurrences for at-least-once delivery after commit.
 *
 * This is not an atomic outbox: consumers must remain idempotent.
 */
final class QueuedEventMessage implements ShouldQueue
{
    use Queueable;

    /** @var array<string, mixed> */
    private array $message;

    /**
     * Constructs QueuedEventMessage
     */
    public function __construct(EventMessage $message)
    {
        $this->message = $message->arraySerialize();
        $this->afterCommit();
    }

    /**
     * Reconstitutes and synchronously dispatches the queued event occurrence
     */
    public function handle(EventMessageHandler $handler): void
    {
        $handler(EventMessage::arrayDeserialize($this->message));
    }
}
