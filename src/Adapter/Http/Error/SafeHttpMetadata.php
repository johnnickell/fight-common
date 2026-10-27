<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Error;

use InvalidArgumentException;

/**
 * Class SafeHttpMetadata
 *
 * Deliberately selected protocol metadata; unrelated exception headers are never forwarded.
 */
final readonly class SafeHttpMetadata
{
    /**
     * Constructs SafeHttpMetadata
     */
    public function __construct(
        /**
         * @deprecated Use challenge() instead; retained until a future major after at least one released minor
         */
        public ?string $challenge = null,
        /**
         * @deprecated Use allow() instead; retained until a future major after at least one released minor
         */
        public ?string $allow = null,
        /**
         * @deprecated Use retryAfterSeconds() instead; retained until a future major after at least one released minor
         */
        public ?int $retryAfterSeconds = null
    ) {
        if ($challenge !== null && !$this->validChallenge($challenge)) {
            throw new InvalidArgumentException('Invalid authentication challenge.');
        }

        $method = "[!#$%&'*+.^_`|~0-9A-Za-z-]+";
        if ($allow !== null && preg_match('/\A'.$method.'(?:, '.$method.')*\z/D', $allow) !== 1) {
            throw new InvalidArgumentException('Invalid Allow methods.');
        }

        if ($retryAfterSeconds !== null && $retryAfterSeconds < 0) {
            throw new InvalidArgumentException('Invalid Retry-After seconds.');
        }
    }

    /**
     * Returns the selected authentication challenge
     */
    public function challenge(): ?string
    {
        return $this->challenge;
    }

    /**
     * Returns the selected Allow methods
     */
    public function allow(): ?string
    {
        return $this->allow;
    }

    /**
     * Returns the selected retry delay in seconds
     */
    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    /**
     * Validates a single HTTP authentication challenge without header injection
     */
    private function validChallenge(string $challenge): bool
    {
        $token = "[!#$%&'*+.^_`|~0-9A-Za-z-]+";
        if (preg_match('/\A'.$token.'(?: ([\x20-\x7e]+))?\z/D', $challenge, $matches) !== 1) {
            return false;
        }

        if (!isset($matches[1])) {
            return true;
        }

        $quoted = '"(?:[\x20-\x21\x23-\x5b\x5d-\x7e]|\\\\[\x20-\x7e])*"';
        $parameter = $token.'=(?:'.$token.'|'.$quoted.')';
        $credentials = '[A-Za-z0-9._~+\/-]+=*';

        return preg_match('/\A(?:'.$credentials.'|'.$parameter.'(?:, *'.$parameter.')*)\z/D', $matches[1]) === 1;
    }
}
