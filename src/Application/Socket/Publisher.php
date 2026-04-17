<?php

declare(strict_types=1);

namespace Fight\Common\Application\Socket;

use Fight\Common\Application\Socket\Exception\SocketException;

/**
 * Interface Publisher
 */
interface Publisher
{
    /**
     * Pushes a socket message
     *
     * @throws SocketException When an error occurs
     */
    public function push(string $topic, string $message): void;
}
