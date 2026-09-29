<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\OAuth;

use Fight\Common\Application\Auth\OAuth\OAuthResourceMetadata;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class OAuthMetadataHandler
 */
final readonly class OAuthMetadataHandler implements RequestHandlerInterface
{
    /**
     * Constructs OAuthMetadataHandler
     */
    public function __construct(private OAuthResourceMetadata $metadata, private OAuthResponseFactory $responses)
    {
    }

    /**
     * Handles a metadata GET on the route owned by the consumer
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() !== 'GET') {
            return $this->responses->methodNotAllowed();
        }

        return $this->responses->metadata($this->metadata);
    }
}
