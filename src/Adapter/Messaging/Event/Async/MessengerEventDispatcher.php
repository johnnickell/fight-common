<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Event\Async;

use Fight\Common\Application\Messaging\Event\AsynchronousEventDispatcher;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/**
 * Class MessengerEventDispatcher
 */
final readonly class MessengerEventDispatcher implements AsynchronousEventDispatcher
{
    /**
     * Constructs MessengerEventDispatcher
     */
    public function __construct(private SenderInterface $sender)
    {
    }

    /**
     * @inheritDoc
     */
    public function trigger(Event $event): void
    {
        $this->dispatch(EventMessage::create($event));
    }

    /**
     * @inheritDoc
     */
    public function dispatch(EventMessage $eventMessage): void
    {
        $this->sender->send(new Envelope($eventMessage));
    }

    /**
     * @inheritDoc
     */
    public function register(EventSubscriber $subscriber): void
    {
        // no-op: async dispatcher sends to transport, handlers not stored locally
    }

    /**
     * @inheritDoc
     */
    public function unregister(EventSubscriber $subscriber): void
    {
        // no-op: async dispatcher sends to transport, handlers not stored locally
    }

    /**
     * @inheritDoc
     */
    public function addHandler(string $eventType, callable $handler, int $priority = 0): void
    {
        // no-op: async dispatcher sends to transport, handlers not stored locally
    }

    /**
     * @inheritDoc
     */
    public function getHandlers(?string $eventType = null): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function hasHandlers(?string $eventType = null): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function removeHandler(string $eventType, callable $handler): void
    {
        // no-op: async dispatcher sends to transport, handlers not stored locally
    }
}
