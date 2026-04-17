<?php

declare(strict_types=1);

namespace Fight\Common\Application\HttpClient\Message;

use Fight\Common\Domain\Exception\DomainException;
use Psr\Http\Message\StreamInterface;

/**
 * Interface StreamFactory
 */
interface StreamFactory
{
    /**
     * Creates a StreamInterface instance
     *
     * @param string|resource|null $body Content body
     *
     * @throws DomainException When the body is invalid
     */
    public function createStream(mixed $body = null): StreamInterface;
}
