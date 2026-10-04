<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth\OAuth;

use SensitiveParameter;

/**
 * Interface OAuthClaimsHandoff
 */
interface OAuthClaimsHandoff
{
    /**
     * Handles validated claims in consumer-owned request-scoped authentication composition
     *
     * Resolve authoritative identity and establish context before returning. A failure prevents downstream
     * dispatch. Consumers own context lifetime, cleanup, permission decisions and any later authorization.
     */
    public function accept(#[SensitiveParameter] OAuthValidatedClaims $claims): void;
}
