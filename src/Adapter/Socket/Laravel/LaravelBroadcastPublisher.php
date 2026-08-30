<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Socket\Laravel;

use Fight\Common\Application\Socket\Exception\SocketException;
use Fight\Common\Application\Socket\Publisher;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Throwable;

/**
 * Class LaravelBroadcastPublisher
 *
 * Publishes Fight socket messages through Laravel's configured broadcaster.
 */
final readonly class LaravelBroadcastPublisher implements Publisher
{
    /**
     * Constructs LaravelBroadcastPublisher
     */
    public function __construct(
        private Broadcaster $broadcaster,
        private string $eventName
    ) {
    }

    /**
     * @inheritDoc
     */
    public function push(string $topic, string $message): void
    {
        try {
            $this->broadcaster->broadcast([$topic], $this->eventName, ['message' => $message]);
        } catch (Throwable $throwable) {
            throw new SocketException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }
}
