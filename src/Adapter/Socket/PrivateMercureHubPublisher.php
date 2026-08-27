<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Socket;

use Fight\Common\Application\Socket\Exception\SocketException;
use Fight\Common\Application\Socket\PrivatePublisher;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

/**
 * Class PrivateMercureHubPublisher
 */
final readonly class PrivateMercureHubPublisher implements PrivatePublisher
{
    /**
     * Constructs PrivateMercureHubPublisher
     */
    public function __construct(private HubInterface $hub)
    {
    }

    /**
     * @inheritDoc
     */
    public function pushPrivate(string $topic, string $message): void
    {
        $update = new Update($topic, $message, true);

        try {
            $this->hub->publish($update);
        } catch (Throwable $throwable) {
            throw new SocketException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }
}
