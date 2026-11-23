<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Common\Adapter\Http\OAuth\OAuthMetadataHandler;
use Fight\Common\Adapter\Http\OAuth\OAuthMiddleware;
use Fight\Common\Adapter\Http\OAuth\OAuthResponseFactory;
use Fight\Common\Adapter\Mcp\Http\DenyAllMcpOriginPolicy;
use Fight\Common\Adapter\Mcp\Http\McpRequestHandler;
use Fight\Common\Adapter\Mcp\Http\McpResponseFactory;
use Fight\Common\Application\Auth\OAuth\OAuthClaimsHandoff;
use Fight\Common\Application\Auth\OAuth\OAuthDiagnostics;
use Fight\Common\Application\Auth\OAuth\OAuthFailure;
use Fight\Common\Application\Auth\OAuth\OAuthResourceServer;
use Fight\Common\Application\Auth\OAuth\OAuthScopeSet;
use Fight\Common\Application\Auth\OAuth\OAuthTokenRequirements;
use Fight\Common\Application\Auth\OAuth\OAuthTokenValidator;
use Fight\Common\Application\Auth\OAuth\OAuthValidatedClaims;
use Fight\Common\Application\Mcp\Capability\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\Http\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\Protocol\McpServerInfo;
use Fight\Test\Common\Fixture\Mcp\EndpointCapability;
use Fight\Test\Common\Fixture\OAuth\OAuthFixture;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversNothing]
final class OAuthMcpJourneyTest extends TestCase
{
    #[DataProvider('journeys')]
    public function test_that_oauth_protects_the_real_guarded_endpoint_without_semantic_dispatch_on_failure(string $scenario, int $status, ?string $error, ?OAuthFailure $classification): void
    {
        $factory = new HttpFactory();
        $capability = new EndpointCapability();
        $sequence = [];
        $requirements = OAuthFixture::requirements();
        $validator = $this->createMock(OAuthTokenValidator::class);
        $validator->expects(self::once())->method('validate')->willReturnCallback(function (string $credential, OAuthTokenRequirements $passed, int $now) use ($requirements, &$sequence): OAuthValidatedClaims {
            self::assertSame('fixture-token', $credential);
            self::assertSame($requirements, $passed);
            self::assertGreaterThan(0, $now);
            $sequence[] = 'validate';
            return OAuthFixture::claims();
        });
        $handoff = $this->createMock(OAuthClaimsHandoff::class);
        $handoff->expects($scenario === 'valid' ? self::once() : self::never())->method('accept')->willReturnCallback(function (OAuthValidatedClaims $claims) use (&$sequence): void {
            $sequence[] = 'handoff';
            self::assertSame('fixture-subject', $claims->claims()->get('sub'));
            self::assertFalse($claims->claims()->has('credential'));
        });
        $diagnostics = new class implements OAuthDiagnostics {
            public array $records = [];
            public function record(OAuthFailure $classification, ?Throwable $failure): void
            {
                $this->records[] = ['classification' => $classification->name, 'failure_class' => $failure === null ? null : $failure::class, 'message' => $failure === null ? null : '[redacted]', 'trace' => $failure === null ? [] : array_map(static fn(array $frame): string => $frame['function'], $failure->getTrace())];
            }
        };
        $endpointDiagnostics = $this->createMock(McpDiagnostics::class);
        $endpointDiagnostics->expects(self::never())->method('record');
        $guard = $this->createMock(McpInvocationGuard::class);
        $guard->expects($scenario === 'valid' ? self::once() : self::never())->method('allows')->willReturnCallback(function () use (&$sequence): bool {
            self::assertSame(['validate', 'handoff'], $sequence);
            $sequence[] = 'guard';
            return true;
        });
        $endpoint = new McpRequestHandler(new McpCapabilityRegistry(new McpServerInfo('OAuth fixture', '1'), [$capability]), new DenyAllMcpOriginPolicy(), $guard, new McpResponseFactory($factory, $factory, $endpointDiagnostics));
        $oauthResponseFactory = new OAuthResponseFactory($factory, $factory);
        $server = new OAuthResourceServer($requirements, $validator, $handoff, $diagnostics);
        $scopes = OAuthScopeSet::fromArray($scenario === 'scope' ? ['mcp:write', 'mcp:read'] : ['mcp:read']);
        $middleware = new OAuthMiddleware($server, $scopes, $oauthResponseFactory);
        $request = $this->request()->withHeader('Authorization', 'Bearer fixture-token');
        $metadataResponse = (new OAuthMetadataHandler($requirements->metadata(), $oauthResponseFactory))->handle(new ServerRequest('GET', $requirements->metadata()->metadataUrl()));
        self::assertSame(200, $metadataResponse->getStatusCode());
        self::assertSame(OAuthFixture::RESOURCE, json_decode((string) $metadataResponse->getBody(), true)['resource']);
        $response = $middleware->process($request, $endpoint);
        self::assertSame($status, $response->getStatusCode(), $scenario);
        self::assertSame($scenario === 'valid' ? 1 : 0, $capability->handleCalls);
        self::assertSame($capability->handleCalls, $capability->validateCalls);
        self::assertCount($classification === null ? 0 : 1, $diagnostics->records);
        if ($classification !== null) {
            self::assertSame($classification->name, $diagnostics->records[0]['classification']);
            self::assertSame('', (string) $response->getBody());
        }
        if ($status === 400 || $status === 401 || $status === 403) {
            self::assertStringContainsString('resource_metadata="'.$requirements->metadata()->metadataUrl().'"', $response->getHeaderLine('WWW-Authenticate'));
            self::assertStringContainsString('scope="'.implode(' ', $scopes->values()).'"', $response->getHeaderLine('WWW-Authenticate'));
            if ($error === null) { self::assertStringNotContainsString('error=', $response->getHeaderLine('WWW-Authenticate')); }
            else { self::assertStringContainsString('error="'.$error.'"', $response->getHeaderLine('WWW-Authenticate')); }
        }
        $public = (string) $response->getBody().json_encode($response->getHeaders()).json_encode($diagnostics->records);
        foreach (['fixture-token', 'secret-token', 'private-claim', 'downstream-private'] as $secret) { self::assertStringNotContainsString($secret, $public); }
        if ($scenario === 'valid') { self::assertSame(['resultType' => 'complete', 'message' => 'accepted', '_meta' => ['io.modelcontextprotocol/serverInfo' => ['name' => 'OAuth fixture', 'version' => '1']]], json_decode((string) $response->getBody(), true)['result']); }
    }

    public static function journeys(): iterable
    {
        yield ['valid', 200, null, null];
        yield ['scope', 403, 'insufficient_scope', OAuthFailure::INSUFFICIENT_SCOPE];
    }

    private function request(): ServerRequest
    {
        return new ServerRequest('POST', OAuthFixture::RESOURCE, ['Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'example/echo'], json_encode(['jsonrpc' => '2.0', 'id' => 7, 'method' => 'example/echo', 'params' => ['_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]]], JSON_THROW_ON_ERROR));
    }
}
