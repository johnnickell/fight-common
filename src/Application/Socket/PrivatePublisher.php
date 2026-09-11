<?php

declare(strict_types=1);

namespace Fight\Common\Application\Socket;

use Fight\Common\Application\Socket\Exception\SocketException;

/**
 * Interface PrivatePublisher
 */
interface PrivatePublisher
{
    /**
     * Sends a private socket message
     *
     * @throws SocketException When an error occurs
     */
    public function pushPrivate(string $topic, string $message): void;
}
