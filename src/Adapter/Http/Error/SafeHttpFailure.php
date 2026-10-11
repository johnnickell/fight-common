<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Error;

use RuntimeException;
use Throwable;

/**
 * Class SafeHttpFailure
 *
 * Explicit adapter-owned HTTP outcomes. Not an Application exception or a
 * translation of arbitrary exception codes/headers.
 */
final class SafeHttpFailure extends RuntimeException
{
    /**
     * Constructs SafeHttpFailure
     */
    private function __construct(
        public readonly int $status,
        public readonly ?SafeHttpMetadata $metadata = null,
        string $diagnostic = '',
        ?Throwable $previous = null
    ) {
        parent::__construct($diagnostic, 0, $previous);
    }

    /**
     * Creates an invalid-request outcome
     */
    public static function badRequest(string $diagnostic = '', ?Throwable $previous = null): self
    {
        return new self(400, diagnostic: $diagnostic, previous: $previous);
    }

    /**
     * Creates an authentication outcome with a consumer-owned challenge
     */
    public static function authenticationRequired(string $challenge, string $diagnostic = ''): self
    {
        return new self(401, new SafeHttpMetadata(challenge: $challenge), $diagnostic);
    }

    /**
     * Creates a method rejection with a required Allow value
     */
    public static function methodNotAllowed(string $allow, string $diagnostic = ''): self
    {
        return new self(405, new SafeHttpMetadata(allow: $allow), $diagnostic);
    }

    /**
     * Creates a negotiation rejection
     */
    public static function notAcceptable(string $diagnostic = ''): self
    {
        return new self(406, diagnostic: $diagnostic);
    }

    /**
     * Creates a request-size rejection
     */
    public static function payloadTooLarge(string $diagnostic = ''): self
    {
        return new self(413, diagnostic: $diagnostic);
    }

    /**
     * Creates a media-type rejection
     */
    public static function unsupportedMediaType(string $diagnostic = ''): self
    {
        return new self(415, diagnostic: $diagnostic);
    }

    /**
     * Creates a transport-owned rate-limit rejection
     */
    public static function tooManyRequests(?int $retryAfterSeconds = null, string $diagnostic = ''): self
    {
        return new self(429, new SafeHttpMetadata(retryAfterSeconds: $retryAfterSeconds), $diagnostic);
    }

    /**
     * Creates a transport-owned availability failure
     */
    public static function unavailable(?int $retryAfterSeconds = null, string $diagnostic = ''): self
    {
        return new self(503, new SafeHttpMetadata(retryAfterSeconds: $retryAfterSeconds), $diagnostic);
    }
}
