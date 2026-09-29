<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth\OAuth;

use SensitiveParameter;
use Throwable;

/**
 * Class OAuthResourceServer
 */
final readonly class OAuthResourceServer
{
    /**
     * Constructs OAuthResourceServer
     */
    public function __construct(
        public OAuthTokenRequirements $requirements,
        private OAuthTokenValidator $validator,
        private OAuthClaimsHandoff $handoff,
        private OAuthDiagnostics $diagnostics
    ) {
    }

    /**
     * Validates one header-only request and completes the consumer handoff before permitting dispatch
     *
     * Required scopes are supplied by consumer composition before semantic request selection.
     * Unsupported transport indicates a query credential or a form-encoded token transport attempt.
     * Null means success; every failure has been diagnosed exactly once and forbids downstream dispatch.
     *
     * @phpstan-param array<array-key, mixed> $authorization
     */
    public function authorize(
        #[SensitiveParameter] array $authorization,
        OAuthScopeSet $requiredScopes,
        bool $unsupportedTransport = false
    ): ?OAuthFailure {
        if (
            $unsupportedTransport || count($authorization) > 1 || !array_is_list($authorization)
            || ($authorization !== [] && !is_string($authorization[0]))
        ) {
            return $this->reject(OAuthFailure::INVALID_REQUEST);
        }

        if ($authorization === [] || $authorization[0] === '') {
            return $this->reject(OAuthFailure::MISSING_CREDENTIALS);
        }

        $header = $authorization[0];
        if (
            preg_match('/\A([A-Za-z][A-Za-z0-9_-]*)(?:[ \t]+[^,\r\n]+)?\z/', $header, $scheme) === 1
            && strcasecmp($scheme[1], 'Bearer') !== 0
        ) {
            return $this->reject(OAuthFailure::MISSING_CREDENTIALS);
        }

        if (preg_match('~\ABearer +([A-Za-z0-9._+/\x7e-]+=*)\z~i', $header, $matches) !== 1) {
            return $this->reject(OAuthFailure::INVALID_REQUEST);
        }

        try {
            $now = time();
            try {
                $claims = $this->validator->validate($matches[1], $this->requirements, $now);
            } catch (OAuthTokenRejected) {
                return $this->reject(OAuthFailure::INVALID_TOKEN);
            }

            if (!$this->requirements->accepts($claims, $now)) {
                return $this->reject(OAuthFailure::INVALID_TOKEN);
            }

            if (!$claims->grantedScopes->includes($requiredScopes)) {
                return $this->reject(OAuthFailure::INSUFFICIENT_SCOPE);
            }

            $this->handoff->accept($claims);
        } catch (Throwable $throwable) {
            return $this->reject(OAuthFailure::INTERNAL_ERROR, $throwable);
        }

        return null;
    }

    /**
     * Records one rejection without allowing a broken sink to expose diagnostics
     */
    private function reject(OAuthFailure $classification, ?Throwable $failure = null): OAuthFailure
    {
        try {
            $this->diagnostics->record($classification, $failure);
        } catch (Throwable) {
            // A failed diagnostic sink must not turn a denied request into dispatch or disclose its failure.
        }

        return $classification;
    }
}
