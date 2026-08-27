<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Psr17;

use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Class JSendResponseFactory
 *
 * Creates PSR-7 JSend responses from their already-encoded semantic envelopes.
 */
final readonly class JSendResponseFactory
{
    /**
     * Constructs JSendResponseFactory
     */
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory
    ) {
    }

    /**
     * Creates a response from an already-encoded semantic envelope
     *
     * @param JSendEnvelope                  $envelope   Semantic JSend envelope.
     * @param integer                        $statusCode Controller-selected HTTP status.
     * @param array<string, string|string[]> $headers Controller-selected HTTP headers.
     */
    public function fromEnvelope(JSendEnvelope $envelope, int $statusCode, array $headers = []): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($statusCode);

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($envelope->toJson()));
    }
}
