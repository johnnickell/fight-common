<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth\OAuth;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use SensitiveParameter;

/**
 * Class OAuthValidatedClaims
 *
 * A trusted validator attests cryptographic validation before constructing this value.
 * Construction alone is not proof of token authenticity. No raw credential belongs in these claims.
 */
final readonly class OAuthValidatedClaims
{
    /**
     * Constructs OAuthValidatedClaims
     */
    private function __construct(
        public StrictJson $claims,
        public string $algorithm,
        public string $keyId,
        public string $tokenType,
        public OAuthScopeSet $grantedScopes
    ) {
    }

    /**
     * Creates an immutable snapshot after complete consumer token verification
     *
     * Normalize issuer, audiences, expiration and not-before to iss, aud, exp and nbf respectively.
     * Expand issuer-defined scope hierarchies into grantedScopes after validation, never from request data.
     */
    public static function fromVerifiedToken(
        #[SensitiveParameter] StrictJson $claims,
        string $algorithm,
        string $keyId,
        string $tokenType,
        OAuthScopeSet $grantedScopes
    ): self {
        if (!$claims->isObject() || $algorithm === '' || $keyId === '' || $tokenType === '') {
            throw new DomainException('Incomplete OAuth validated claims.');
        }

        return new self($claims, $algorithm, $keyId, $tokenType, $grantedScopes);
    }
}
