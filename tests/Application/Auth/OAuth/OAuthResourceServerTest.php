<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Auth\OAuth;

use Fight\Common\Application\Auth\OAuth\OAuthClaimsHandoff;
use Fight\Common\Application\Auth\OAuth\OAuthDiagnostics;
use Fight\Common\Application\Auth\OAuth\OAuthFailure;
use Fight\Common\Application\Auth\OAuth\OAuthResourceServer;
use Fight\Common\Application\Auth\OAuth\OAuthScopeSet;
use Fight\Common\Application\Auth\OAuth\OAuthTokenRejected;
use Fight\Common\Application\Auth\OAuth\OAuthTokenRequirements;
use Fight\Common\Application\Auth\OAuth\OAuthTokenValidator;
use Fight\Common\Application\Auth\OAuth\OAuthValidatedClaims;
use Fight\Test\Common\Fixture\OAuth\OAuthFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(OAuthResourceServer::class)]
#[CoversClass(OAuthTokenRejected::class)]
final class OAuthResourceServerTest extends UnitTestCase
{
    #[DataProvider('rejectedHeaders')]
    public function test_that_transport_failures_never_validate_or_hand_off_claims(array $headers, bool $unsupported, OAuthFailure $expected): void
    {
        $validator = $this->createMock(OAuthTokenValidator::class);
        $validator->expects(self::never())->method('validate');
        $handoff = $this->createMock(OAuthClaimsHandoff::class);
        $handoff->expects(self::never())->method('accept');
        $diagnostics = $this->createMock(OAuthDiagnostics::class);
        $diagnostics->expects(self::once())->method('record')->with($expected, null);
        $server = new OAuthResourceServer(OAuthFixture::requirements(), $validator, $handoff, $diagnostics);
        self::assertSame($expected, $server->authorize($headers, OAuthScopeSet::fromArray([]), $unsupported));
    }

    public static function rejectedHeaders(): iterable
    {
        foreach ([[], [''], ['Basic abc'], ['HMAC-SHA256'], ['Unknown abc']] as $headers) { yield [$headers, false, OAuthFailure::MISSING_CREDENTIALS]; }
        foreach ([['Bearer'], ['Bearer '], ['Bearer\ttoken'], ['Bearer token, Bearer other'], ['Bearer token other'], ['Bearer token', 'Bearer other'], ['Bearer a=b'], ['Bearer ='], ['Bearer to%ken'], ["Bearer token\n"], ['bearer' => 'Bearer token']] as $headers) { yield [$headers, false, OAuthFailure::INVALID_REQUEST]; }
        yield [['Bearer token'], true, OAuthFailure::INVALID_REQUEST];
        yield [[], true, OAuthFailure::INVALID_REQUEST];
    }

    #[DataProvider('validHeaders')]
    public function test_that_one_credential_and_every_requirement_reach_validation_before_claims_only_handoff(string $header, string $credential): void
    {
        $requirements = OAuthFixture::requirements();
        $claims = OAuthFixture::claims();
        $sequence = [];
        $validator = $this->createMock(OAuthTokenValidator::class);
        $validator->expects(self::once())->method('validate')->willReturnCallback(function (string $token, OAuthTokenRequirements $passed, int $now) use ($requirements, $credential, $claims, &$sequence): OAuthValidatedClaims {
            self::assertSame($credential, $token);
            self::assertSame($requirements, $passed);
            self::assertSame(OAuthFixture::RESOURCE, $passed->metadata->resource);
            self::assertSame([OAuthFixture::ISSUER], $passed->metadata->authorizationServers);
            self::assertSame('at+jwt', $passed->tokenType);
            self::assertSame(['RS256'], $passed->allowedAlgorithms);
            self::assertSame([OAuthFixture::ISSUER => ['current', 'overlap']], $passed->activeKeyIds);
            self::assertSame(['sub' => 'string'], $passed->requiredClaimTypes);
            self::assertSame(0, $passed->clockSkewSeconds);
            self::assertLessThanOrEqual(time(), $now);
            self::assertGreaterThanOrEqual(time() - 2, $now);
            $sequence[] = 'validate';
            return $claims;
        });
        $handoff = $this->createMock(OAuthClaimsHandoff::class);
        $handoff->expects(self::once())->method('accept')->with($claims)->willReturnCallback(function () use (&$sequence): void { $sequence[] = 'handoff'; });
        $diagnostics = $this->createMock(OAuthDiagnostics::class);
        $diagnostics->expects(self::never())->method('record');
        $server = new OAuthResourceServer($requirements, $validator, $handoff, $diagnostics);
        self::assertNull($server->authorize([$header], OAuthScopeSet::fromArray(['mcp:read'])));
        self::assertSame(['validate', 'handoff'], $sequence);
    }

    public static function validHeaders(): iterable
    {
        yield ['Bearer token', 'token'];
        yield ['bEaReR   a-Z_0.~+/==', 'a-Z_0.~+/=='];
    }

    #[DataProvider('invalidEvidence')]
    public function test_that_intrinsic_failure_always_precedes_scope_comparison(array $replace, string $algorithm, string $key, string $type): void
    {
        $validator = $this->createMock(OAuthTokenValidator::class);
        $validator->expects(self::once())->method('validate')->willReturn(OAuthFixture::claims($replace, $algorithm, $key, $type, []));
        $handoff = $this->createMock(OAuthClaimsHandoff::class);
        $handoff->expects(self::never())->method('accept');
        $diagnostics = $this->createMock(OAuthDiagnostics::class);
        $diagnostics->expects(self::once())->method('record')->with(OAuthFailure::INVALID_TOKEN, null);
        $server = new OAuthResourceServer(OAuthFixture::requirements(), $validator, $handoff, $diagnostics);
        self::assertSame(OAuthFailure::INVALID_TOKEN, $server->authorize(['Bearer fixture'], OAuthScopeSet::fromArray(['mcp:read'])));
    }

    public static function invalidEvidence(): iterable
    {
        yield from OAuthConfigurationTest::invalidClaims();
    }

    public function test_that_only_missing_operation_scope_is_insufficient_scope_not_an_invalid_token(): void
    {
        $validator = $this->createMock(OAuthTokenValidator::class);
        $validator->expects(self::exactly(2))->method('validate')->willReturn(OAuthFixture::claims());
        $handoff = $this->createMock(OAuthClaimsHandoff::class);
        $handoff->expects(self::once())->method('accept');
        $diagnostics = $this->createMock(OAuthDiagnostics::class);
        $diagnostics->expects(self::once())->method('record')->with(OAuthFailure::INSUFFICIENT_SCOPE, null);
        $server = new OAuthResourceServer(OAuthFixture::requirements(), $validator, $handoff, $diagnostics);
        self::assertNull($server->authorize(['Bearer same-token'], OAuthScopeSet::fromArray(['mcp:read'])));
        self::assertSame(OAuthFailure::INSUFFICIENT_SCOPE, $server->authorize(['Bearer same-token'], OAuthScopeSet::fromArray(['mcp:read', 'mcp:write'])));
    }

    public function test_that_validator_rejection_has_no_sensitive_reason_and_diagnostics_cannot_enable_dispatch(): void
    {
        $failure = new OAuthTokenRejected();
        self::assertSame('OAuth token rejected.', $failure->getMessage());
        self::assertNull($failure->getPrevious());
        $validator = $this->createMock(OAuthTokenValidator::class);
        $validator->expects(self::once())->method('validate')->willThrowException($failure);
        $handoff = $this->createMock(OAuthClaimsHandoff::class);
        $handoff->expects(self::never())->method('accept');
        $diagnostics = $this->createMock(OAuthDiagnostics::class);
        $diagnostics->expects(self::once())->method('record')->with(OAuthFailure::INVALID_TOKEN, null)->willThrowException(new RuntimeException('private sink detail'));
        self::assertSame(OAuthFailure::INVALID_TOKEN, (new OAuthResourceServer(OAuthFixture::requirements(), $validator, $handoff, $diagnostics))->authorize(['Bearer secret'], OAuthScopeSet::fromArray([])));
    }

    #[DataProvider('unexpectedFailures')]
    public function test_that_unexpected_validation_and_handoff_failures_are_recorded_once_without_public_details(bool $handoffFailure): void
    {
        $failure = new RuntimeException('private implementation detail');
        $validator = $this->createMock(OAuthTokenValidator::class);
        $validation = $validator->expects(self::once())->method('validate');
        $handoff = $this->createMock(OAuthClaimsHandoff::class);
        if ($handoffFailure) {
            $validation->willReturn(OAuthFixture::claims());
            $handoff->expects(self::once())->method('accept')->willThrowException($failure);
        } else {
            $validation->willThrowException($failure);
            $handoff->expects(self::never())->method('accept');
        }
        $diagnostics = $this->createMock(OAuthDiagnostics::class);
        $diagnostics->expects(self::once())->method('record')->with(OAuthFailure::INTERNAL_ERROR, $failure);
        self::assertSame(OAuthFailure::INTERNAL_ERROR, (new OAuthResourceServer(OAuthFixture::requirements(), $validator, $handoff, $diagnostics))->authorize(['Bearer secret'], OAuthScopeSet::fromArray(['mcp:read'])));
    }

    public static function unexpectedFailures(): iterable
    {
        yield [true];
        yield [false];
    }
}
