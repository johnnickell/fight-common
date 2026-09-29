<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\OAuth;

use Fight\Common\Application\Auth\OAuth\OAuthResourceServer;
use Fight\Common\Application\Auth\OAuth\OAuthScopeSet;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class OAuthMiddleware
 */
final readonly class OAuthMiddleware implements MiddlewareInterface
{
    /**
     * Constructs OAuthMiddleware
     *
     * Compose per consumer resource operation, never select scopes from an untrusted MCP method or Tool.
     */
    public function __construct(
        private OAuthResourceServer $server,
        private OAuthScopeSet $requiredScopes,
        private OAuthResponseFactory $responses
    ) {
    }

    /**
     * Handles protection without adding raw credentials or claims to semantic requests
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $failure = $this->server->authorize(
            $request->getHeader('Authorization'),
            $this->requiredScopes,
            $this->unsupportedTransport($request)
        );
        if ($failure !== null) {
            return $this->responses->rejection($failure, $this->server->requirements->metadata, $this->requiredScopes);
        }

        // Downstream owns its error diagnostics; do not catch and log those failures a second time.
        return $handler->handle($request);
    }

    /**
     * Rejects unsupported credential transports without reading a potentially unbounded request body
     */
    private function unsupportedTransport(ServerRequestInterface $request): bool
    {
        foreach (explode('&', $request->getUri()->getQuery()) as $parameter) {
            $name = urldecode(explode('=', $parameter, 2)[0]);
            if ($name === 'access_token' || str_starts_with($name, 'access_token[')) {
                return true;
            }
        }

        if (array_key_exists('access_token', $request->getQueryParams())) {
            return true;
        }

        return array_any(
            $request->getHeader('Content-Type'),
            static fn (string $contentType): bool =>
                strtolower(trim(explode(';', $contentType, 2)[0])) === 'application/x-www-form-urlencoded'
        );
    }
}
