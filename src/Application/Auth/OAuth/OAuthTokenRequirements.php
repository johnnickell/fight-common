<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth\OAuth;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use SensitiveParameter;

/**
 * Class OAuthTokenRequirements
 *
 * Signature, exact issuer/resource, expiration, not-before, purpose, algorithm, active issuer-bound key
 * and claim shape checks are mandatory. There are no switches to disable them.
 */
final readonly class OAuthTokenRequirements
{
    /**
     * Constructs OAuthTokenRequirements
     *
     * @phpstan-param non-empty-list<string> $allowedAlgorithms
     * @phpstan-param array<string, non-empty-list<string>> $activeKeyIds
     * @phpstan-param non-empty-array<string, 'string'|'integer'|'boolean'|'number'|'object'|'list'> $requiredClaimTypes
     */
    private function __construct(
        public OAuthResourceMetadata $metadata,
        public string $tokenType,
        public array $allowedAlgorithms,
        public array $activeKeyIds,
        public array $requiredClaimTypes,
        public int $clockSkewSeconds
    ) {
    }

    /**
     * Creates explicit requirements including the currently accepted rotation overlap for each issuer
     *
     * Key IDs identify trusted consumer keys, not URLs to fetch from untrusted token headers.
     * Additional required claim types are string, integer, boolean, number, object or list.
     *
     * @phpstan-param array<array-key, mixed> $allowedAlgorithms
     * @phpstan-param array<string, array<array-key, mixed>> $activeKeyIds
     * @phpstan-param array<array-key, mixed> $requiredClaimTypes
     */
    public static function fromConfiguration(
        OAuthResourceMetadata $metadata,
        string $tokenType,
        array $allowedAlgorithms,
        array $activeKeyIds,
        array $requiredClaimTypes,
        int $clockSkewSeconds = 0
    ): self {
        self::validateNames([$tokenType]);
        $allowedAlgorithms = self::validateNames($allowedAlgorithms);
        if (in_array('none', array_map(strtolower(...), $allowedAlgorithms), true)) {
            throw new DomainException('Unsigned OAuth tokens are not supported.');
        }

        if (count($activeKeyIds) !== count($metadata->authorizationServers)) {
            throw new DomainException('OAuth requires active keys for every configured issuer.');
        }

        $keys = [];
        foreach ($metadata->authorizationServers as $issuer) {
            $keys[$issuer] = self::validateNames($activeKeyIds[$issuer] ?? []);
        }

        if ($requiredClaimTypes === [] || $clockSkewSeconds < 0 || $clockSkewSeconds > 300) {
            throw new DomainException('Invalid OAuth claim or time requirements.');
        }

        $claimTypes = [];
        foreach ($requiredClaimTypes as $name => $type) {
            if (
                !is_string($name) || $name === '' || preg_match('//u', $name) !== 1
                || !in_array($type, ['string', 'integer', 'boolean', 'number', 'object', 'list'], true)
            ) {
                throw new DomainException('Invalid OAuth claim shape requirement.');
            }

            $claimTypes[$name] = $type;
        }

        return new self(
            $metadata,
            $tokenType,
            $allowedAlgorithms,
            $keys,
            $claimTypes,
            $clockSkewSeconds
        );
    }

    /**
     * Returns whether trusted normalized claims satisfy every non-cryptographic requirement
     *
     * This defense in depth never replaces the consumer validator's cryptographic verification.
     */
    public function accepts(#[SensitiveParameter] OAuthValidatedClaims $validated, int $now): bool
    {
        $claims = $validated->claims;
        $issuer = $claims->get('iss');
        if (
            !is_string($issuer) || !in_array($issuer, $this->metadata->authorizationServers, true)
            || $validated->tokenType !== $this->tokenType
            || !in_array($validated->algorithm, $this->allowedAlgorithms, true)
            || !in_array($validated->keyId, $this->activeKeyIds[$issuer], true)
        ) {
            return false;
        }

        $audiences = $claims->get('aud');
        if (is_string($audiences)) {
            $audiences = [$audiences];
        }

        if (!is_array($audiences) || !in_array($this->metadata->resource, $audiences, true)) {
            return false;
        }

        foreach ($audiences as $audience) {
            if (!is_string($audience) || $audience === '') {
                return false;
            }
        }

        $expires = $claims->get('exp');
        $notBefore = $claims->get('nbf');
        if (
            !is_int($expires) || !is_int($notBefore) || $notBefore >= $expires
            || $expires <= $now - $this->clockSkewSeconds || $notBefore > $now + $this->clockSkewSeconds
        ) {
            return false;
        }

        foreach ($this->requiredClaimTypes as $name => $type) {
            $value = $claims->get($name);
            $matches = match ($type) {
                'string' => is_string($value),
                'integer' => is_int($value),
                'boolean' => is_bool($value),
                'number' => is_int($value) || is_float($value),
                'object' => $value instanceof StrictJson,
                'list' => is_array($value),
            };
            if (!$matches) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validates a nonempty list of unique visible ASCII policy identifiers
     *
     * @phpstan-param array<array-key, mixed> $names
     * @phpstan-return non-empty-list<string>
     */
    private static function validateNames(array $names): array
    {
        if ($names === [] || !array_is_list($names)) {
            throw new DomainException('OAuth validation requirements cannot be empty.');
        }

        $seen = [];
        foreach ($names as $name) {
            if (!is_string($name) || preg_match('/\A[\x21-\x7e]+\z/', $name) !== 1 || in_array($name, $seen, true)) {
                throw new DomainException('Invalid or duplicate OAuth validation requirement.');
            }

            $seen[] = $name;
        }

        return $seen;
    }
}
