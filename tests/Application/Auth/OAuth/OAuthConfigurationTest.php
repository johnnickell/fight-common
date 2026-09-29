<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Auth\OAuth;

use Fight\Common\Application\Auth\OAuth\OAuthResourceMetadata;
use Fight\Common\Application\Auth\OAuth\OAuthScopeSet;
use Fight\Common\Application\Auth\OAuth\OAuthTokenRequirements;
use Fight\Common\Application\Auth\OAuth\OAuthValidatedClaims;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\Fixture\OAuth\OAuthFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(OAuthResourceMetadata::class)]
#[CoversClass(OAuthScopeSet::class)]
#[CoversClass(OAuthTokenRequirements::class)]
#[CoversClass(OAuthValidatedClaims::class)]
final class OAuthConfigurationTest extends UnitTestCase
{
    public function test_that_metadata_preserves_all_issuers_and_exact_resource_identity(): void
    {
        $issuers = ['https://second.test/tenant', OAuthFixture::ISSUER];
        $metadata = OAuthFixture::metadata($issuers);
        self::assertSame($issuers, $metadata->authorizationServers);
        self::assertSame('https://resource.test/.well-known/oauth-protected-resource/mcp', $metadata->metadataUrl);
        self::assertSame(['resource' => OAuthFixture::RESOURCE, 'authorization_servers' => $issuers, 'scopes_supported' => ['mcp:read'], 'bearer_methods_supported' => ['header']], json_decode($metadata->toJson(), true));
        $requirements = OAuthTokenRequirements::fromConfiguration($metadata, 'at+jwt', ['RS256'], ['https://second.test/tenant' => ['tenant-key'], OAuthFixture::ISSUER => ['current']], ['sub' => 'string']);
        self::assertTrue($requirements->accepts(OAuthFixture::claims(), time()));
        self::assertTrue($requirements->accepts(OAuthFixture::claims(['iss' => 'https://second.test/tenant'], key: 'tenant-key'), time()));
        self::assertFalse($requirements->accepts(OAuthFixture::claims(['iss' => 'https://second.test/tenant']), time()));
    }

    public function test_that_metadata_omits_empty_advertised_scopes(): void
    {
        $issuers = ['https://second.test/tenant', OAuthFixture::ISSUER];
        $metadata = OAuthResourceMetadata::fromConfiguration(OAuthFixture::RESOURCE, $issuers, OAuthScopeSet::fromArray([]));
        self::assertSame([
            'resource' => OAuthFixture::RESOURCE,
            'authorization_servers' => $issuers,
            'bearer_methods_supported' => ['header']
        ], json_decode($metadata->toJson(), true));
    }

    #[DataProvider('metadataLocations')]
    public function test_that_well_known_path_insertion_preserves_resource_identity(string $resource, string $expected): void
    {
        $metadata = OAuthResourceMetadata::fromConfiguration($resource, [OAuthFixture::ISSUER], OAuthScopeSet::fromArray([]));
        self::assertSame($resource, $metadata->resource);
        self::assertSame($expected, $metadata->metadataUrl);
        self::assertSame('https://metadata.test/custom', OAuthResourceMetadata::fromConfiguration($resource, [OAuthFixture::ISSUER], OAuthScopeSet::fromArray([]), 'https://metadata.test/custom')->metadataUrl);
    }

    public static function metadataLocations(): iterable
    {
        yield ['https://resource.test', 'https://resource.test/.well-known/oauth-protected-resource'];
        yield ['https://resource.test/', 'https://resource.test/.well-known/oauth-protected-resource'];
        yield ['https://resource.test/tenant/mcp/?id=1', 'https://resource.test/.well-known/oauth-protected-resource/tenant/mcp?id=1'];
        yield ['HTTPS://RESOURCE.TEST:8443/Mcp', 'HTTPS://RESOURCE.TEST:8443/.well-known/oauth-protected-resource/Mcp'];
        yield ['https://resource.test/a%2Fb', 'https://resource.test/.well-known/oauth-protected-resource/a%2Fb'];
    }

    #[DataProvider('invalidMetadata')]
    public function test_that_metadata_rejects_invalid_composition(string $resource, array $issuers, ?string $url): void
    {
        $this->expectException(DomainException::class);
        OAuthResourceMetadata::fromConfiguration($resource, $issuers, OAuthScopeSet::fromArray([]), $url);
    }

    public static function invalidMetadata(): iterable
    {
        foreach (['', 'http://resource.test', 'relative', 'https://u:p@resource.test', 'https://resource.test/#fragment', "https://resource.test/\r\n", 'https://resource.test/a%zz', 'https://resource.test/"', 'https://resource.test/\\', 'https://resource.test/é'] as $url) {
            yield [$url, [OAuthFixture::ISSUER], null];
            yield [OAuthFixture::RESOURCE, [OAuthFixture::ISSUER], $url];
        }
        foreach ([[], [OAuthFixture::ISSUER, OAuthFixture::ISSUER], ['https://issuer.test?query'], ['http://issuer.test'], [17], ['issuer' => OAuthFixture::ISSUER]] as $issuers) {
            yield [OAuthFixture::RESOURCE, $issuers, null];
        }
    }

    public function test_that_scopes_are_sorted_case_sensitive_sets_with_no_policy_inference(): void
    {
        $scopes = OAuthScopeSet::fromArray(['write', 'Read', '0', 'read']);
        self::assertSame(['0', 'Read', 'read', 'write'], $scopes->values);
        self::assertTrue($scopes->includes(OAuthScopeSet::fromArray(['read', 'write'])));
        self::assertTrue($scopes->includes(OAuthScopeSet::fromArray([])));
        self::assertFalse($scopes->includes(OAuthScopeSet::fromArray(['admin'])));
        self::assertFalse(OAuthScopeSet::fromArray(['read'])->includes(OAuthScopeSet::fromArray(['Read'])));
    }

    #[DataProvider('invalidScopes')]
    public function test_that_scope_composition_rejects_unsafe_malformed_or_duplicate_values(array $scopes): void
    {
        $this->expectException(DomainException::class);
        OAuthScopeSet::fromArray($scopes);
    }

    public static function invalidScopes(): iterable
    {
        foreach ([['a', 'a'], [''], ['two scopes'], ['a"'], ['a\\'], ["a\r\n"], ['é'], [1], ['name' => 'scope']] as $scopes) { yield [$scopes]; }
    }

    #[DataProvider('invalidRequirements')]
    public function test_that_missing_or_invalid_validation_requirements_fail_during_composition(array $replace): void
    {
        $arguments = array_replace(['metadata' => OAuthFixture::metadata(), 'tokenType' => 'at+jwt', 'allowedAlgorithms' => ['RS256'], 'activeKeyIds' => [OAuthFixture::ISSUER => ['current']], 'requiredClaimTypes' => ['sub' => 'string'], 'clockSkewSeconds' => 0], $replace);
        $this->expectException(DomainException::class);
        OAuthTokenRequirements::fromConfiguration(...$arguments);
    }

    public static function invalidRequirements(): iterable
    {
        foreach (['', ' ', "type\n"] as $type) { yield [['tokenType' => $type]]; }
        foreach ([[], ['none'], ['NONE'], ['RS256', 'RS256'], [17], ['alg' => 'RS256'], ["RS256\n"]] as $algorithms) { yield [['allowedAlgorithms' => $algorithms]]; }
        foreach ([[], [OAuthFixture::ISSUER => []], ['https://wrong.test' => ['key']], [OAuthFixture::ISSUER => ['current', 'current']]] as $keys) { yield [['activeKeyIds' => $keys]]; }
        foreach ([[], ['sub' => 'anything'], ['' => 'string'], [0 => 'string'], ["\xff" => 'string']] as $claims) { yield [['requiredClaimTypes' => $claims]]; }
        foreach ([-1, 301] as $skew) { yield [['clockSkewSeconds' => $skew]]; }
    }

    #[DataProvider('invalidClaims')]
    public function test_that_partial_or_invalid_claim_evidence_cannot_satisfy_requirements(array $replace, string $algorithm, string $key, string $type): void
    {
        self::assertFalse(OAuthFixture::requirements()->accepts(OAuthFixture::claims($replace, $algorithm, $key, $type), time()));
    }

    public static function invalidClaims(): iterable
    {
        foreach ([['iss' => null], ['iss' => 'https://wrong.test'], ['aud' => null], ['aud' => 'https://resource.test/other'], ['aud' => [OAuthFixture::RESOURCE, 17]], ['aud' => [OAuthFixture::RESOURCE, '']], ['exp' => null], ['exp' => '9999999999'], ['nbf' => null], ['exp' => 1], ['nbf' => PHP_INT_MAX], ['sub' => null], ['sub' => 5]] as $claims) { yield [$claims, 'RS256', 'current', 'at+jwt']; }
        yield [[], 'HS256', 'current', 'at+jwt'];
        yield [[], 'RS256', 'retired', 'at+jwt'];
        yield [[], 'RS256', 'current', 'id+jwt'];
    }

    public function test_that_time_boundaries_rotation_audiences_and_claim_shapes_are_explicit(): void
    {
        $requirements = OAuthTokenRequirements::fromConfiguration(OAuthFixture::metadata(), 'at+jwt', ['RS256'], [OAuthFixture::ISSUER => ['overlap', 'current']], ['sub' => 'string', 'count' => 'integer', 'active' => 'boolean', 'ratio' => 'number', 'context' => 'object', 'items' => 'list'], 10);
        $values = ['aud' => ['https://other.test', OAuthFixture::RESOURCE], 'exp' => 100, 'nbf' => 50, 'count' => 2, 'active' => true, 'ratio' => 1.5, 'context' => (object) ['id' => 'private'], 'items' => []];
        $claims = OAuthFixture::claims($values, key: 'overlap');
        self::assertTrue($requirements->accepts($claims, 90));
        self::assertTrue($requirements->accepts($claims, 109));
        self::assertFalse($requirements->accepts($claims, 110));
        self::assertTrue($requirements->accepts($claims, 40));
        self::assertFalse($requirements->accepts($claims, 39));
        self::assertTrue($requirements->accepts(OAuthFixture::claims(array_replace($values, ['ratio' => 1])), 90));
        foreach (['count', 'active', 'ratio', 'context', 'items'] as $name) {
            self::assertFalse($requirements->accepts(OAuthFixture::claims(array_replace($values, [$name => null])), 90));
        }
        $values['context']->id = 'mutated';
        self::assertSame('private', $claims->claims->get('context')->get('id'));
        self::assertSame('overlap', $claims->keyId);
    }

    public function test_that_configuration_snapshots_do_not_retain_caller_array_references(): void
    {
        $issuer = OAuthFixture::ISSUER;
        $issuers = [&$issuer];
        $metadata = OAuthResourceMetadata::fromConfiguration(OAuthFixture::RESOURCE, $issuers, OAuthScopeSet::fromArray([]));
        $key = 'current';
        $keys = [&$key];
        $type = 'string';
        $requirements = OAuthTokenRequirements::fromConfiguration($metadata, 'at+jwt', ['RS256'], [OAuthFixture::ISSUER => &$keys], ['sub' => &$type]);
        $issuer = 'http://changed.test';
        $keys = ['attacker-key'];
        $type = 'integer';
        self::assertSame([OAuthFixture::ISSUER], $metadata->authorizationServers);
        self::assertSame([OAuthFixture::ISSUER => ['current']], $requirements->activeKeyIds);
        self::assertSame(['sub' => 'string'], $requirements->requiredClaimTypes);
        self::assertTrue($requirements->accepts(OAuthFixture::claims(), time()));
        self::assertFalse($requirements->accepts(OAuthFixture::claims(key: 'attacker-key'), time()));
    }

    public function test_that_validated_claims_require_an_object_and_complete_verification_identifiers(): void
    {
        foreach ([[StrictJson::fromData([]), 'RS256', 'key', 'at+jwt'], [StrictJson::fromObject(), '', 'key', 'at+jwt'], [StrictJson::fromObject(), 'RS256', '', 'at+jwt'], [StrictJson::fromObject(), 'RS256', 'key', '']] as [$claims, $algorithm, $key, $type]) {
            try {
                OAuthValidatedClaims::fromVerifiedToken($claims, $algorithm, $key, $type, OAuthScopeSet::fromArray([]));
                self::fail('Incomplete verification identifiers accepted');
            } catch (DomainException) { $this->addToAssertionCount(1); }
        }
    }
}
