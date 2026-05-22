<?php

declare(strict_types=1);

namespace Fight\Common\Application\HttpClient\Message;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Interface MessageFactory
 */
interface MessageFactory
{
    /**
     * Creates a RequestInterface instance
     */
    public function createRequest(
        string $method,
        string|UriInterface $uri,
        array $headers = [],
        mixed $body = null,
        string $protocol = '1.1'
    ): RequestInterface;

    /**
     * Creates a ResponseInterface instance
     */
    public function createResponse(
        int $status = 200,
        array $headers = [],
        mixed $body = null,
        string $protocol = '1.1',
        ?string $reason = null
    ): ResponseInterface;
}
