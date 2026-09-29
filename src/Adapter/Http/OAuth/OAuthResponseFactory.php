<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\OAuth;

use Fight\Common\Application\Auth\OAuth\OAuthFailure;
use Fight\Common\Application\Auth\OAuth\OAuthResourceMetadata;
use Fight\Common\Application\Auth\OAuth\OAuthScopeSet;
use Fight\Common\Domain\Exception\DomainException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Class OAuthResponseFactory
 */
final readonly class OAuthResponseFactory
{
    /**
     * Constructs OAuthResponseFactory
     */
    public function __construct(
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
        private ?string $realm = null
    ) {
        if ($realm !== null && preg_match('/\A[\x20-\x7e]*\z/', $realm) !== 1) {
            throw new DomainException('Unsafe OAuth challenge realm.');
        }
    }

    /**
     * Creates a public protected-resource metadata response
     */
    public function metadata(OAuthResourceMetadata $metadata): ResponseInterface
    {
        return $this->responses->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streams->createStream($metadata->toJson()));
    }

    /**
     * Creates a transport rejection without a protocol or Tool result
     */
    public function rejection(
        OAuthFailure $failure,
        OAuthResourceMetadata $metadata,
        OAuthScopeSet $requiredScopes
    ): ResponseInterface {
        $status = match ($failure) {
            OAuthFailure::MISSING_CREDENTIALS, OAuthFailure::INVALID_TOKEN => 401,
            OAuthFailure::INVALID_REQUEST => 400,
            OAuthFailure::INSUFFICIENT_SCOPE => 403,
            OAuthFailure::INTERNAL_ERROR => 500,
        };
        $response = $this->responses->createResponse($status)
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($this->streams->createStream());
        if ($failure === OAuthFailure::INTERNAL_ERROR) {
            return $response;
        }

        $parameters = ['resource_metadata='.$this->quote($metadata->metadataUrl)];
        if ($this->realm !== null) {
            $parameters[] = 'realm='.$this->quote($this->realm);
        }

        if ($failure !== OAuthFailure::MISSING_CREDENTIALS) {
            $parameters[] = 'error='.$this->quote(strtolower($failure->name));
        }

        if ($requiredScopes->values !== []) {
            $parameters[] = 'scope='.$this->quote(implode(' ', $requiredScopes->values));
        }

        return $response->withHeader('WWW-Authenticate', 'Bearer '.implode(', ', $parameters));
    }

    /**
     * Creates a metadata method rejection without reading or reflecting request data
     */
    public function methodNotAllowed(): ResponseInterface
    {
        return $this->responses->createResponse(405)
            ->withHeader('Allow', 'GET')
            ->withBody($this->streams->createStream());
    }

    /**
     * Returns one safely quoted authentication parameter
     */
    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
