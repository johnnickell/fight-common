<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\OAuth;

use Fight\Common\Application\Auth\OAuth\OAuthResourceMetadata;
use Fight\Common\Application\Auth\OAuth\OAuthScopeSet;
use Fight\Common\Application\Auth\OAuth\OAuthTokenRequirements;
use Fight\Common\Application\Auth\OAuth\OAuthValidatedClaims;
use Fight\Common\Domain\Value\Basic\StrictJson;

final class OAuthFixture
{
    public const RESOURCE = 'https://resource.test/mcp';
    public const ISSUER = 'https://issuer.test';

    public static function metadata(array $issuers = [self::ISSUER]): OAuthResourceMetadata
    {
        return OAuthResourceMetadata::fromConfiguration(self::RESOURCE, $issuers, OAuthScopeSet::fromArray(['mcp:read']));
    }

    public static function requirements(): OAuthTokenRequirements
    {
        return OAuthTokenRequirements::fromConfiguration(self::metadata(), 'at+jwt', ['RS256'], [self::ISSUER => ['current', 'overlap']], ['sub' => 'string']);
    }

    public static function claims(array $replace = [], string $algorithm = 'RS256', string $key = 'current', string $type = 'at+jwt', array $scopes = ['mcp:read']): OAuthValidatedClaims
    {
        return OAuthValidatedClaims::fromVerifiedToken(StrictJson::fromObject(array_replace([
            'iss' => self::ISSUER, 'aud' => self::RESOURCE, 'exp' => time() + 300, 'nbf' => time() - 60, 'sub' => 'fixture-subject'
        ], $replace)), $algorithm, $key, $type, OAuthScopeSet::fromArray($scopes));
    }
}
