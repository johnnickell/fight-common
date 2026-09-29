<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth\OAuth;

use SensitiveParameter;

/**
 * Interface OAuthTokenValidator
 */
interface OAuthTokenValidator
{
    /**
     * Validates one credential completely before returning immutable neutral claims
     *
     * Verify authenticity/signature using trusted issuer-bound keys and algorithms, active key rotation,
     * issuer, exact audience/resource, expiry, not-before, purpose/type and required claim shape.
     * Normalize verified claims to iss, aud, exp and nbf; return trusted effective granted scopes including
     * issuer-defined hierarchy expansion. Never use request arguments to infer any of these facts.
     * Throw OAuthTokenRejected for any intrinsic token failure, including missing required evidence.
     * Infrastructure failures may throw; redact diagnostics and never retain the credential.
     * A signature-only TokenDecoder or JwtDecoder cannot implement this complete contract by delegation alone.
     */
    public function validate(
        #[SensitiveParameter] string $credential,
        OAuthTokenRequirements $requirements,
        int $now
    ): OAuthValidatedClaims;
}
