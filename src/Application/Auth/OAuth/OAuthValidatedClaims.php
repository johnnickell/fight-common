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
        /**
         * @deprecated Planned v1.5.0; use claims() instead
         * Retained until a future major after at least one released minor
         */
        public StrictJson $claims,
        /**
         * @deprecated Planned v1.5.0; use algorithm() instead
         * Retained until a future major after at least one released minor
         */
        public string $algorithm,
        /**
         * @deprecated Planned v1.5.0; use keyId() instead
         * Retained until a future major after at least one released minor
         */
        public string $keyId,
        /**
         * @deprecated Planned v1.5.0; use tokenType() instead
         * Retained until a future major after at least one released minor
         */
        public string $tokenType,
        /**
         * @deprecated Planned v1.5.0; use grantedScopes() instead
         * Retained until a future major after at least one released minor
         */
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

    /**
     * Returns the immutable verified claims snapshot
     */
    public function claims(): StrictJson
    {
        return $this->claims;
    }

    /**
     * Returns the verified signature algorithm
     */
    public function algorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * Returns the verified key identifier
     */
    public function keyId(): string
    {
        return $this->keyId;
    }

    /**
     * Returns the verified token purpose
     */
    public function tokenType(): string
    {
        return $this->tokenType;
    }

    /**
     * Returns the trusted effective scopes
     */
    public function grantedScopes(): OAuthScopeSet
    {
        return $this->grantedScopes;
    }
}
