<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Socket;

use Fight\Common\Application\Socket\Exception\SocketException;
use Fight\Common\Application\Socket\Publisher;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

/**
 * Class MercureHubPublisher
 */
final readonly class MercureHubPublisher implements Publisher
{
    /**
     * Constructs MercureHubPublisher
     */
    public function __construct(private HubInterface $hub)
    {
    }

    /**
     * @inheritDoc
     */
    public function push(string $topic, string $message): void
    {
        $update = new Update($topic, $message);

        try {
            $this->hub->publish($update);
        } catch (Throwable $e) {
            throw new SocketException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
