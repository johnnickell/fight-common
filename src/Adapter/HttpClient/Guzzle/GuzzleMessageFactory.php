<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\HttpClient\Guzzle;

use Fight\Common\Application\HttpClient\Message\MessageFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class GuzzleMessageFactory
 */
final class GuzzleMessageFactory implements MessageFactory
{
    /**
     * @inheritDoc
     */
    public function createRequest(
        string $method,
        string|UriInterface $uri,
        array $headers = [],
        mixed $body = null,
        string $protocol = '1.1'
    ): RequestInterface {
        return new Request($method, $uri, $headers, $body, $protocol);
    }

    /**
     * @inheritDoc
     */
    public function createResponse(
        int $status = 200,
        array $headers = [],
        mixed $body = null,
        string $protocol = '1.1',
        ?string $reason = null
    ): ResponseInterface {
        return new Response($status, $headers, $body, $protocol, $reason);
    }
}
