<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Middleware\Psr15;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class JsonRequestMiddleware
 *
 * Decodes JSON request bodies for state-changing PSR-7 requests.
 */
final readonly class JsonRequestMiddleware implements MiddlewareInterface
{
    /**
     * Decodes a state-changing JSON request before delegating it
     *
     * @throws JsonException When a JSON request body cannot be decoded
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isJsonStateChange($request)) {
            $decoded = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $request = $request->withParsedBody(is_array($decoded) ? $decoded : []);
        }

        return $handler->handle($request);
    }

    /**
     * Determines whether a request needs JSON parsing
     */
    private function isJsonStateChange(ServerRequestInterface $request): bool
    {
        return in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && str_starts_with($request->getHeaderLine('Content-Type'), 'application/json');
    }
}
