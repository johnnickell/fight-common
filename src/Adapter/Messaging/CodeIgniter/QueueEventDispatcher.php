<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\CodeIgniter;

use CodeIgniter\Queue\Interfaces\QueueInterface;
use Fight\Common\Application\Messaging\Event\AsynchronousEventDispatcher;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageType;
use RuntimeException;

/**
 * Class QueueEventDispatcher
 */
final readonly class QueueEventDispatcher implements AsynchronousEventDispatcher
{
    /**
     * Constructs QueueEventDispatcher
     */
    public function __construct(
        private QueueInterface $queue,
        private string $queueName,
        private string $jobAlias
    ) {
    }

    /**
     * Dispatches one event in a new complete envelope
     */
    public function trigger(Event $event): void
    {
        $this->dispatch(EventMessage::create($event));
    }

    /**
     * Dispatches one complete event envelope
     */
    public function dispatch(EventMessage $eventMessage): void
    {
        $result = $this->queue->push(
            $this->queueName,
            $this->jobAlias,
            [
                'kind'    => MessageType::EVENT->value,
                'message' => $eventMessage->arraySerialize()
            ]
        );

        if (!$result->getStatus()) {
            throw new RuntimeException(sprintf(
                'CodeIgniter Queue could not submit event message: %s',
                $result->getError() ?? 'unknown queue failure'
            ));
        }
    }

    /**
     * Registers no local subscriber for an asynchronous dispatcher
     */
    public function register(EventSubscriber $subscriber): void
    {
    }

    /**
     * Removes no local subscriber from an asynchronous dispatcher
     */
    public function unregister(EventSubscriber $subscriber): void
    {
    }

    /**
     * Adds no local event handler for an asynchronous dispatcher
     */
    public function addHandler(string $eventType, callable $handler, int $priority = 0): void
    {
    }

    /**
     * Retrieves no locally registered handlers
     *
     * @return callable[]
     */
    public function getHandlers(?string $eventType = null): array
    {
        return [];
    }

    /**
     * Returns whether local handlers are registered
     */
    public function hasHandlers(?string $eventType = null): bool
    {
        return false;
    }

    /**
     * Removes no local event handler for an asynchronous dispatcher
     */
    public function removeHandler(string $eventType, callable $handler): void
    {
    }
}
