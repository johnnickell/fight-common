<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Symfony;

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
     * Dispatches an event asynchronously
     *
     * @inheritDoc
     */
    public function trigger(Event $event): void
    {
        $this->dispatch(EventMessage::create($event));
    }

    /**
     * Sends an event message to the transport
     *
     * @inheritDoc
     */
    public function dispatch(EventMessage $eventMessage): void
    {
        $this->sender->send(new Envelope($eventMessage));
    }

    /**
     * Registers no local subscriber for an asynchronous dispatcher
     *
     * @inheritDoc
     */
    public function register(EventSubscriber $subscriber): void
    {
    }

    /**
     * Removes no local subscriber from an asynchronous dispatcher
     *
     * @inheritDoc
     */
    public function unregister(EventSubscriber $subscriber): void
    {
    }

    /**
     * Adds no local event handler for an asynchronous dispatcher
     *
     * @inheritDoc
     */
    public function addHandler(string $eventType, callable $handler, int $priority = 0): void
    {
    }

    /**
     * Retrieves no locally registered handlers for an event or all events
     *
     * @return callable[]
     */
    public function getHandlers(?string $eventType = null): array
    {
        return [];
    }

    /**
     * Returns whether local handlers are registered
     *
     * @inheritDoc
     */
    public function hasHandlers(?string $eventType = null): bool
    {
        return false;
    }

    /**
     * Removes no local event handler for an asynchronous dispatcher
     *
     * @inheritDoc
     */
    public function removeHandler(string $eventType, callable $handler): void
    {
    }
}
