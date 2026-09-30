<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Mcp;

use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpJsonResponse;
use Fight\Common\Application\Mcp\McpOriginPolicy;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Domain\Exception\DomainException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Class McpRequestHandler
 */
final readonly class McpRequestHandler implements RequestHandlerInterface
{
    private McpHeaderValidator $headers;
    private McpResponder $responder;

    /**
     * Constructs McpRequestHandler
     */
    public function __construct(
        McpCapabilityRegistry $registry,
        private McpOriginPolicy $originPolicy,
        private McpInvocationGuard $guard,
        private McpResponseFactory $responses,
        private McpRequestDecoder $decoder = new McpRequestDecoder(),
        private bool $progressive = false
    ) {
        $this->headers = new McpHeaderValidator($registry);
        $this->responder = new McpResponder($registry);
    }

    /**
     * @inheritDoc
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $message = null;
        try {
            if (!$this->allowsOrigin($request)) {
                return $this->responses->rejection(403);
            }

            if ($request->getMethod() !== 'POST') {
                return $this->responses->rejection(405);
            }

            if (!$this->acceptsBoth($request->getHeader('Accept'))) {
                return $this->responses->rejection(406);
            }

            if (!$this->isJson($request->getHeader('Content-Type'))) {
                return $this->responses->rejection(415);
            }

            $json = new McpRequestBodyReader()->read($request->getBody(), $this->decoder->limits());
            if ($json === null) {
                return $this->responses->rejection(413);
            }

            $message = $this->decoder->decode($json);
            $name = $this->headers->validate($message, $request->getHeaders());
            if ($message->metadata()->protocolVersion() !== McpResponder::PROTOCOL_VERSION) {
                return $this->responses->fromResponse(McpJsonResponse::error(
                    $message->id(),
                    McpProtocolError::unsupportedProtocolVersion(
                        [McpResponder::PROTOCOL_VERSION],
                        $message->metadata()->protocolVersion()
                    )
                ));
            }

            if (!$this->guard->allows($message->method(), $name)) {
                return $this->responses->fromResponse(McpJsonResponse::error(
                    $message->id(),
                    McpProtocolError::invocationLimit()
                ));
            }

            if (
                $this->progressive && $message->method() === 'tools/call'
                && $message->metadata()->progressToken() !== null
            ) {
                return $this->responses->stream($message, $this->responder);
            }

            return $this->responses->fromResponse($this->responder->dispatch($message));
        } catch (McpProtocolException $exception) {
            return $this->responses->fromResponse(McpJsonResponse::error(
                $message?->id() ?? $exception->requestId(),
                $exception->protocolError()
            ));
        } catch (Throwable $failure) {
            return $this->responses->failure($failure, $message?->id());
        }
    }

    /**
     * Validates supplied origins before touching the body or selecting a capability
     */
    private function allowsOrigin(ServerRequestInterface $request): bool
    {
        if (!$request->hasHeader('Origin')) {
            return true;
        }

        $origins = $request->getHeader('Origin');
        if (count($origins) !== 1) {
            return false;
        }

        try {
            $origin = McpOrigin::fromString($origins[0]);
        } catch (DomainException) {
            return false;
        }

        return $this->originPolicy->allows($origin->toString());
    }

    /**
     * Returns whether both explicit response media types are acceptable
     *
     * @phpstan-param list<string> $values
     */
    private function acceptsBoth(array $values): bool
    {
        $value = implode(',', $values);
        $token = "[!#$%&'*+.^_`|\\~0-9A-Za-z-]+";
        $quoted = '"(?:[^"\\\\\x00-\x08\x0a-\x1f\x7f]|\\\\[\x09\x20-\x7e])*"';
        $parameter = '[ \t]*;[ \t]*'.$token.'[ \t]*=[ \t]*(?:'.$token.'|'.$quoted.')';
        $pattern = '~\G[ \t]*('.$token.'/'.$token.')((?:'.$parameter.')*)[ \t]*(?:,|$)~';
        $offset = 0;
        $accepted = [];
        while ($offset < strlen($value)) {
            if (preg_match($pattern, $value, $match, offset: $offset) !== 1) {
                return false;
            }

            $offset += strlen($match[0]);
            $mediaType = strtolower($match[1]);
            if ($match[2] !== '') {
                $parameterPattern = '~;[ \t]*('.$token.')[ \t]*=[ \t]*('.$token.'|'.$quoted.')~';
                preg_match_all($parameterPattern, $match[2], $parameters, PREG_SET_ORDER);
                $qualities = [];
                foreach ($parameters as $parameterMatch) {
                    if (strtolower($parameterMatch[1]) === 'q') {
                        $qualities[] = $parameterMatch[2];
                    }
                }

                if (count($qualities) > 1) {
                    return false;
                }

                if ($qualities !== []) {
                    $quality = $qualities[0];
                    if (preg_match('/\A(?:0(?:\.[0-9]{0,3})?|1(?:\.0{0,3})?)\z/', $quality) !== 1) {
                        return false;
                    }

                    if ((float) $quality === 0.0) {
                        continue;
                    }
                }
            }

            $accepted[$mediaType] = true;
        }

        return isset($accepted['application/json'], $accepted['text/event-stream']);
    }

    /**
     * Returns whether the body declares UTF-8 JSON
     *
     * @phpstan-param list<string> $values
     */
    private function isJson(array $values): bool
    {
        $pattern = '/\Aapplication\/json(?:[ \t]*;[ \t]*charset=(?:utf-8|"utf-8"))?[ \t]*\z/i';

        return count($values) === 1 && preg_match($pattern, $values[0]) === 1;
    }
}
