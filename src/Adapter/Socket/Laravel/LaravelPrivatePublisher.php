<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Socket\Laravel;

use Fight\Common\Application\Socket\PrivatePublisher;
use Fight\Common\Application\Socket\Publisher;

/**
 * Class LaravelPrivatePublisher
 *
 * Publishes through Laravel's private-channel naming convention while retaining
 * application-owned channel authorization and broadcaster configuration.
 */
final readonly class LaravelPrivatePublisher implements PrivatePublisher
{
    /**
     * Constructs LaravelPrivatePublisher
     */
    public function __construct(private Publisher $publisher)
    {
    }

    /**
     * @inheritDoc
     */
    public function pushPrivate(string $topic, string $message): void
    {
        $this->publisher->push('private-'.$topic, $message);
    }
}
