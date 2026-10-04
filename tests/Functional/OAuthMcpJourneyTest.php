<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Common\Adapter\Auth\Hmac\HmacAuthenticator;
use Fight\Common\Adapter\Auth\Hmac\HmacRequestService;
use Fight\Common\Adapter\Http\Mcp\DenyAllMcpOriginPolicy;
use Fight\Common\Adapter\Http\Mcp\McpRequestHandler;
use Fight\Common\Adapter\Http\Mcp\McpResponseFactory;
use Fight\Common\Adapter\Http\OAuth\OAuthMetadataHandler;
use Fight\Common\Adapter\Http\OAuth\OAuthMiddleware;
use Fight\Common\Adapter\Http\OAuth\OAuthResponseFactory;
use Fight\Common\Application\Auth\Exception\AuthException;
use Fight\Common\Application\Auth\OAuth\OAuthClaimsHandoff;
use Fight\Common\Application\Auth\OAuth\OAuthDiagnostics;
use Fight\Common\Application\Auth\OAuth\OAuthFailure;
use Fight\Common\Application\Auth\OAuth\OAuthResourceServer;
use Fight\Common\Application\Auth\OAuth\OAuthScopeSet;
use Fight\Common\Application\Auth\OAuth\OAuthTokenRejected;
use Fight\Common\Application\Auth\OAuth\OAuthTokenRequirements;
use Fight\Common\Application\Auth\OAuth\OAuthTokenValidator;
use Fight\Common\Application\Auth\OAuth\OAuthValidatedClaims;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpInvocationGuard;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Domain\Auth\NonceRepository;
use Fight\Test\Common\Fixture\Mcp\EndpointCapability;
use Fight\Test\Common\Fixture\OAuth\OAuthFixture;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

#[CoversNothing]
final class OAuthMcpJourneyTest extends TestCase
{
    #[DataProvider('journeys')]
    public function test_that_oauth_protects_the_real_guarded_endpoint_without_semantic_dispatch_on_failure(string $scenario, int $status, ?string $error, ?OAuthFailure $classification): void
    {
        $factory = new HttpFactory();
        $capability = new EndpointCapability();
        $handoffCalls = 0;
        $sequence = [];
        $requirements = OAuthFixture::requirements();
        $validator = $this->createMock(OAuthTokenValidator::class);
        $preValidation = in_array($scenario, ['missing', 'malformed', 'query', 'body', 'hmac'], true);
        if ($preValidation) {
            $validator->expects(self::never())->method('validate');
        } else {
            $validator->expects(self::once())->method('validate')->willReturnCallback(function (string $credential, OAuthTokenRequirements $passed, int $now) use ($scenario, $requirements, &$sequence): OAuthValidatedClaims {
                self::assertSame('fixture-token', $credential);
                self::assertSame($requirements, $passed);
                self::assertGreaterThan(0, $now);
                $sequence[] = 'validate';
                if ($scenario === 'signature' || $scenario === 'malformed-token') { throw new OAuthTokenRejected(); }
                if ($scenario === 'unexpected') { throw new RuntimeException('secret-token private-claim validation details'); }
                $replace = match ($scenario) {
                    'issuer' => ['iss' => 'https://wrong.test'], 'audience' => ['aud' => 'https://wrong.test'],
                    'expired' => ['exp' => time() - 1], 'premature' => ['nbf' => time() + 200],
                    'shape' => ['sub' => null], default => []
                };
                return OAuthFixture::claims($replace, $scenario === 'algorithm' ? 'HS256' : 'RS256', $scenario === 'key' ? 'retired' : 'current', $scenario === 'purpose' ? 'id+jwt' : 'at+jwt');
            });
        }
        $handoff = $this->createMock(OAuthClaimsHandoff::class);
        $handoff->expects(in_array($scenario, ['valid', 'handoff', 'endpoint-failure'], true) ? self::once() : self::never())->method('accept')->willReturnCallback(function (OAuthValidatedClaims $claims) use ($scenario, &$handoffCalls, &$sequence): void {
            ++$handoffCalls;
            $sequence[] = 'handoff';
            self::assertSame('fixture-subject', $claims->claims->get('sub'));
            self::assertFalse($claims->claims->has('credential'));
            if ($scenario === 'handoff') { throw new RuntimeException('private-claim context resolution failure'); }
        });
        $diagnostics = new class implements OAuthDiagnostics {
            public array $records = [];
            public function record(OAuthFailure $classification, ?Throwable $failure): void
            {
                $this->records[] = ['classification' => $classification->name, 'failure_class' => $failure === null ? null : $failure::class, 'message' => $failure === null ? null : '[redacted]', 'trace' => $failure === null ? [] : array_map(static fn(array $frame): string => $frame['function'], $failure->getTrace())];
            }
        };
        $endpointDiagnostics = $this->createMock(McpDiagnostics::class);
        $endpointDiagnostics->expects($scenario === 'endpoint-failure' ? self::once() : self::never())->method('record');
        if ($scenario === 'endpoint-failure') { $capability->handlingFailure = new RuntimeException('downstream-private'); }
        $guard = $this->createMock(McpInvocationGuard::class);
        $guard->expects(in_array($scenario, ['valid', 'endpoint-failure'], true) ? self::once() : self::never())->method('allows')->willReturnCallback(function () use (&$sequence): bool {
            self::assertSame(['validate', 'handoff'], $sequence);
            $sequence[] = 'guard';
            return true;
        });
        $endpoint = new McpRequestHandler(new McpCapabilityRegistry(new McpServerInfo('OAuth fixture', '1'), [$capability]), new DenyAllMcpOriginPolicy(), $guard, new McpResponseFactory($factory, $factory, $endpointDiagnostics));
        $responses = new OAuthResponseFactory($factory, $factory);
        $server = new OAuthResourceServer($requirements, $validator, $handoff, $diagnostics);
        $scopes = OAuthScopeSet::fromArray($scenario === 'scope' ? ['mcp:write', 'mcp:read'] : ['mcp:read']);
        $middleware = new OAuthMiddleware($server, $scopes, $responses);
        $request = $this->request()->withHeader('Authorization', 'Bearer fixture-token');
        $request = match ($scenario) {
            'missing' => $request->withoutHeader('Authorization'),
            'malformed' => $request->withAddedHeader('Authorization', 'Bearer second-token'),
            'query' => $request->withUri($request->getUri()->withQuery('access_token=secret-token')),
            'body' => $request->withoutHeader('Authorization')->withHeader('Content-Type', 'application/x-www-form-urlencoded')->withBody($factory->createStream('access_token=secret-token')),
            'hmac' => $request->withHeader('Authorization', 'HMAC-SHA256'), default => $request
        };
        $metadataResponse = (new OAuthMetadataHandler($requirements->metadata, $responses))->handle(new ServerRequest('GET', $requirements->metadata->metadataUrl));
        self::assertSame(200, $metadataResponse->getStatusCode());
        self::assertSame(OAuthFixture::RESOURCE, json_decode((string) $metadataResponse->getBody(), true)['resource']);
        $response = $middleware->process($request, $endpoint);
        self::assertSame($status, $response->getStatusCode(), $scenario);
        self::assertSame(in_array($scenario, ['valid', 'endpoint-failure'], true) ? 1 : 0, $capability->handleCalls);
        self::assertSame($capability->handleCalls, $capability->validateCalls);
        self::assertCount($classification === null ? 0 : 1, $diagnostics->records);
        if ($classification !== null) {
            self::assertSame($classification->name, $diagnostics->records[0]['classification']);
            self::assertSame('', (string) $response->getBody());
            if ($classification === OAuthFailure::INTERNAL_ERROR) { self::assertNotEmpty($diagnostics->records[0]['trace']); }
        }
        if ($status === 400 || $status === 401 || $status === 403) {
            self::assertStringContainsString('resource_metadata="'.$requirements->metadata->metadataUrl.'"', $response->getHeaderLine('WWW-Authenticate'));
            self::assertStringContainsString('scope="'.implode(' ', $scopes->values).'"', $response->getHeaderLine('WWW-Authenticate'));
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
        yield ['missing', 401, null, OAuthFailure::MISSING_CREDENTIALS];
        yield ['hmac', 401, null, OAuthFailure::MISSING_CREDENTIALS];
        foreach (['malformed', 'query', 'body'] as $scenario) { yield [$scenario, 400, 'invalid_request', OAuthFailure::INVALID_REQUEST]; }
        foreach (['signature', 'malformed-token', 'issuer', 'audience', 'expired', 'premature', 'shape', 'algorithm', 'key', 'purpose'] as $scenario) { yield [$scenario, 401, 'invalid_token', OAuthFailure::INVALID_TOKEN]; }
        yield ['scope', 403, 'insufficient_scope', OAuthFailure::INSUFFICIENT_SCOPE];
        foreach (['handoff', 'unexpected'] as $scenario) { yield [$scenario, 500, null, OAuthFailure::INTERNAL_ERROR]; }
        yield ['endpoint-failure', 500, null, null];
    }

    public function test_that_separate_hmac_composition_preserves_signatures_and_nonce_consumption_without_bearer_fallback(): void
    {
        $private = str_repeat('ab', 32);
        $signer = new HmacRequestService('fixture-key', $private, static fn(int $size): string => 'fixture-nonce');
        $signed = $signer->signRequest($this->request());
        self::assertSame('HMAC-SHA256', $signed->getHeaderLine('Authorization'));
        $nonces = $this->createMock(NonceRepository::class);
        $nonces->expects(self::once())->method('consume');
        $hmac = new HmacAuthenticator('fixture-key', $private, 60, $nonces);
        $validator = $this->createMock(OAuthTokenValidator::class);
        $validator->expects(self::never())->method('validate');
        $handoff = $this->createMock(OAuthClaimsHandoff::class);
        $handoff->expects(self::never())->method('accept');
        $diagnostics = $this->createMock(OAuthDiagnostics::class);
        $diagnostics->expects(self::once())->method('record')->with(OAuthFailure::MISSING_CREDENTIALS, null);
        $bearer = new OAuthResourceServer(OAuthFixture::requirements(), $validator, $handoff, $diagnostics);
        self::assertSame(OAuthFailure::MISSING_CREDENTIALS, $bearer->authorize($signed->getHeader('Authorization'), OAuthScopeSet::fromArray([])));
        self::assertTrue($hmac->validate($signed));
        try {
            $hmac->validate($signed->withHeader('Signature', 'invalid'));
            self::fail('Invalid HMAC signature accepted');
        } catch (AuthException) { $this->addToAssertionCount(1); }
        try {
            $hmac->validate($this->request()->withHeader('Authorization', 'Bearer fixture-token'));
            self::fail('A Bearer credential was accepted by HMAC');
        } catch (AuthException) { $this->addToAssertionCount(1); }
    }

    private function request(): ServerRequest
    {
        return new ServerRequest('POST', OAuthFixture::RESOURCE, ['Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'example/echo'], json_encode(['jsonrpc' => '2.0', 'id' => 7, 'method' => 'example/echo', 'params' => ['_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]]], JSON_THROW_ON_ERROR));
    }
}
