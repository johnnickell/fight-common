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
     * Creates a Guzzle HTTP request
     *
     * @param string $method
     * @param string|UriInterface $uri
     * @param array<string, string|string[]> $headers
     * @param mixed $body
     * @param string $protocol
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
     * Creates a Guzzle HTTP response
     *
     * @param integer $status
     * @param array<string, string|string[]> $headers
     * @param mixed $body
     * @param string $protocol
     * @param string|null $reason
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
