<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Laravel;

use Fight\Common\Application\Messaging\Event\AsynchronousEventDispatcher;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Illuminate\Contracts\Bus\Dispatcher;

/**
 * Class LaravelEventDispatcher
 *
 * Submits complete Fight event messages to Laravel Queue after commit.
 */
final readonly class LaravelEventDispatcher implements AsynchronousEventDispatcher
{
    /**
     * Constructs LaravelEventDispatcher
     */
    public function __construct(private Dispatcher $dispatcher)
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
        $this->dispatcher->dispatch(new QueuedEventMessage($eventMessage));
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
