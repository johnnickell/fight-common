<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth\OAuth;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class OAuthResourceMetadata
 */
final readonly class OAuthResourceMetadata
{
    /**
     * Constructs OAuthResourceMetadata
     *
     * @phpstan-param non-empty-list<string> $authorizationServers
     */
    private function __construct(
        /**
         * @deprecated Use resource() instead; retained until a future major after at least one released minor
         */
        public string $resource,
        /**
         * @deprecated Use metadataUrl() instead; retained until a future major after at least one released minor
         */
        public string $metadataUrl,
        /**
         * @deprecated Use authorizationServers() instead
         * Retained until a future major after at least one released minor
         */
        public array $authorizationServers,
        /**
         * @deprecated Use scopesSupported() instead; retained until a future major after at least one released minor
         */
        public OAuthScopeSet $scopesSupported
    ) {
    }

    /**
     * Creates metadata without selecting an issuer or owning its publication route
     *
     * An explicit metadata URL is advertised by challenges. The default follows RFC 9728 path insertion.
     *
     * @phpstan-param array<array-key, mixed> $authorizationServers
     */
    public static function fromConfiguration(
        string $resource,
        array $authorizationServers,
        OAuthScopeSet $scopesSupported,
        ?string $metadataUrl = null
    ): self {
        self::validateUrl($resource);
        if ($authorizationServers === [] || !array_is_list($authorizationServers)) {
            throw new DomainException('OAuth requires at least one authorization server.');
        }

        $seen = [];
        foreach ($authorizationServers as $issuer) {
            if (!is_string($issuer)) {
                throw new DomainException('Invalid OAuth issuer.');
            }

            self::validateUrl($issuer);
            if (str_contains($issuer, '?') || in_array($issuer, $seen, true)) {
                throw new DomainException('Invalid or duplicate OAuth issuer.');
            }

            $seen[] = $issuer;
        }

        if ($metadataUrl === null) {
            preg_match('~\A(https://[^/?]+)([^?]*)(.*)\z~i', $resource, $parts);
            $metadataUrl = $parts[1].'/.well-known/oauth-protected-resource'.rtrim($parts[2], '/').$parts[3];
        }

        self::validateUrl($metadataUrl);

        return new self($resource, $metadataUrl, $seen, $scopesSupported);
    }

    /**
     * Returns the configured protected resource identifier
     */
    public function resource(): string
    {
        return $this->resource;
    }

    /**
     * Returns the configured metadata URL
     */
    public function metadataUrl(): string
    {
        return $this->metadataUrl;
    }

    /**
     * Returns the configured authorization servers
     *
     * @phpstan-return non-empty-list<string>
     */
    public function authorizationServers(): array
    {
        return $this->authorizationServers;
    }

    /**
     * Returns the configured supported scopes
     */
    public function scopesSupported(): OAuthScopeSet
    {
        return $this->scopesSupported;
    }

    /**
     * Returns only configured public metadata with header-only Bearer transport
     */
    public function toJson(): string
    {
        $metadata = [
            'resource'              => $this->resource,
            'authorization_servers' => $this->authorizationServers
        ];
        if ($this->scopesSupported->values() !== []) {
            $metadata['scopes_supported'] = $this->scopesSupported->values();
        }

        $metadata['bearer_methods_supported'] = ['header'];

        return StrictJson::fromObject($metadata)->toString();
    }

    /**
     * Validates an absolute HTTPS identifier without user information or fragments
     */
    private static function validateUrl(string $url): void
    {
        if (
            filter_var($url, FILTER_VALIDATE_URL) === false
            || preg_match('~\Ahttps://~i', $url) !== 1
            || preg_match('/[^A-Za-z0-9\-._~:\/?\[\]@!$&\x27()*+,;=%]/', $url) === 1
            || preg_match('/%(?![0-9a-f]{2})/i', $url) === 1
            || parse_url($url, PHP_URL_USER) !== null
        ) {
            throw new DomainException('Invalid OAuth HTTPS URL.');
        }
    }
}
