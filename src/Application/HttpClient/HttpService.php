<?php

declare(strict_types=1);

namespace Fight\Common\Application\HttpClient;

use Fight\Common\Application\HttpClient\Message\MessageFactory;
use Fight\Common\Application\HttpClient\Message\Promise;
use Fight\Common\Application\HttpClient\Message\StreamFactory;
use Fight\Common\Application\HttpClient\Message\UriFactory;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class HttpService
 */
final readonly class HttpService implements HttpClient, MessageFactory, StreamFactory, UriFactory
{
    /**
     * Constructs HttpService
     */
    public function __construct(
        private HttpClient $httpClient,
        private MessageFactory $messageFactory,
        private StreamFactory $streamFactory,
        private UriFactory $uriFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        return $this->httpClient->send($request, $options);
    }

    /**
     * @inheritDoc
     */
    public function sendAsync(RequestInterface $request, array $options = []): Promise
    {
        return $this->httpClient->sendAsync($request, $options);
    }

    /**
     * @inheritDoc
     */
    public function createRequest(
        string $method,
        UriInterface|string $uri,
        array $headers = [],
        mixed $body = null,
        string $protocol = '1.1'
    ): RequestInterface {
        return $this->messageFactory->createRequest(
            $method,
            $uri,
            $headers,
            $body,
            $protocol
        );
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
        return $this->messageFactory->createResponse(
            $status,
            $headers,
            $body,
            $protocol,
            $reason
        );
    }

    /**
     * @inheritDoc
     */
    public function createStream(mixed $body = null): StreamInterface
    {
        return $this->streamFactory->createStream($body);
    }

    /**
     * @inheritDoc
     */
    public function createUri(string $uri): UriInterface
    {
        return $this->uriFactory->createUri($uri);
    }
}
