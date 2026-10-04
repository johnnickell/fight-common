<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\OAuth;

use Fight\Common\Adapter\Http\OAuth\OAuthMetadataHandler;
use Fight\Common\Adapter\Http\OAuth\OAuthMiddleware;
use Fight\Common\Adapter\Http\OAuth\OAuthResponseFactory;
use Fight\Common\Application\Auth\OAuth\OAuthClaimsHandoff;
use Fight\Common\Application\Auth\OAuth\OAuthDiagnostics;
use Fight\Common\Application\Auth\OAuth\OAuthFailure;
use Fight\Common\Application\Auth\OAuth\OAuthResourceMetadata;
use Fight\Common\Application\Auth\OAuth\OAuthResourceServer;
use Fight\Common\Application\Auth\OAuth\OAuthScopeSet;
use Fight\Common\Application\Auth\OAuth\OAuthTokenRejected;
use Fight\Common\Application\Auth\OAuth\OAuthTokenValidator;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\Fixture\OAuth\OAuthFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

#[CoversClass(OAuthMiddleware::class)]
#[CoversClass(OAuthMetadataHandler::class)]
#[CoversClass(OAuthResponseFactory::class)]
final class OAuthHttpTest extends UnitTestCase
{
    public function test_that_metadata_get_has_exact_configured_json_and_other_methods_are_rejected(): void
    {
        $factory = new HttpFactory();
        $metadata = OAuthFixture::metadata();
        $handler = new OAuthMetadataHandler($metadata, new OAuthResponseFactory($factory, $factory));
        $response = $handler->handle(new ServerRequest('GET', $metadata->metadataUrl));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame($metadata->toJson(), (string) $response->getBody());
        self::assertFalse($response->hasHeader('Set-Cookie'));
        foreach (['POST', 'HEAD', 'OPTIONS'] as $method) {
            $response = $handler->handle(new ServerRequest($method, $metadata->metadataUrl));
            self::assertSame(405, $response->getStatusCode());
            self::assertSame('GET', $response->getHeaderLine('Allow'));
            self::assertSame('', (string) $response->getBody());
        }
    }

    public function test_that_metadata_get_omits_empty_advertised_scopes(): void
    {
        $factory = new HttpFactory();
        $issuers = ['https://second.test/tenant', OAuthFixture::ISSUER];
        $metadata = OAuthResourceMetadata::fromConfiguration(OAuthFixture::RESOURCE, $issuers, OAuthScopeSet::fromArray([]));
        $handler = new OAuthMetadataHandler($metadata, new OAuthResponseFactory($factory, $factory));
        $response = $handler->handle(new ServerRequest('GET', $metadata->metadataUrl));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame([
            'resource' => OAuthFixture::RESOURCE,
            'authorization_servers' => $issuers,
            'bearer_methods_supported' => ['header']
        ], json_decode((string) $response->getBody(), true));
    }

    #[DataProvider('failures')]
    public function test_that_challenges_preserve_distinct_statuses_all_scopes_and_public_safety(OAuthFailure $failure, int $status, ?string $error): void
    {
        $factory = new HttpFactory();
        $responses = new OAuthResponseFactory($factory, $factory, 'consumer "realm" \\ scope');
        $response = $responses->rejection($failure, OAuthFixture::metadata(), OAuthScopeSet::fromArray(['write', 'read']));
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        if ($status === 500) {
            self::assertFalse($response->hasHeader('WWW-Authenticate'));
            return;
        }
        $expected = 'Bearer resource_metadata="https://resource.test/.well-known/oauth-protected-resource/mcp", realm="consumer \\"realm\\" \\\\ scope"';
        if ($error !== null) { $expected .= ', error="'.$error.'"'; }
        $expected .= ', scope="read write"';
        self::assertSame([$expected], $response->getHeader('WWW-Authenticate'));
        self::assertFalse($response->hasHeader('Content-Type'));
    }

    public static function failures(): iterable
    {
        yield [OAuthFailure::MISSING_CREDENTIALS, 401, null];
        yield [OAuthFailure::INVALID_REQUEST, 400, 'invalid_request'];
        yield [OAuthFailure::INVALID_TOKEN, 401, 'invalid_token'];
        yield [OAuthFailure::INSUFFICIENT_SCOPE, 403, 'insufficient_scope'];
        yield [OAuthFailure::INTERNAL_ERROR, 500, null];
    }

    public function test_that_an_empty_scope_requirement_omits_scope_and_absent_realm_is_not_invented(): void
    {
        $factory = new HttpFactory();
        $response = (new OAuthResponseFactory($factory, $factory))->rejection(OAuthFailure::MISSING_CREDENTIALS, OAuthFixture::metadata(), OAuthScopeSet::fromArray([]));
        self::assertSame('Bearer resource_metadata="https://resource.test/.well-known/oauth-protected-resource/mcp"', $response->getHeaderLine('WWW-Authenticate'));
    }

    public function test_that_unsafe_challenge_configuration_fails_before_serving_requests(): void
    {
        $factory = new HttpFactory();
        $this->expectException(DomainException::class);
        new OAuthResponseFactory($factory, $factory, "realm\r\nInjected: secret");
    }

    #[DataProvider('unsupportedTransports')]
    public function test_that_unsupported_transport_rejects_before_body_read_validation_or_dispatch(string $url, array $headers, array $query): void
    {
        $factory = new HttpFactory();
        $validator = $this->createMock(OAuthTokenValidator::class);
        $validator->expects(self::never())->method('validate');
        $handoff = $this->createMock(OAuthClaimsHandoff::class);
        $handoff->expects(self::never())->method('accept');
        $diagnostics = $this->createMock(OAuthDiagnostics::class);
        $diagnostics->expects(self::once())->method('record')->with(OAuthFailure::INVALID_REQUEST, null);
        $body = $this->createMock(StreamInterface::class);
        $body->expects(self::never())->method('__toString');
        $body->expects(self::never())->method('read');
        $request = (new ServerRequest('POST', $url, $headers))->withQueryParams($query)->withBody($body);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');
        $server = new OAuthResourceServer(OAuthFixture::requirements(), $validator, $handoff, $diagnostics);
        $response = (new OAuthMiddleware($server, OAuthScopeSet::fromArray([]), new OAuthResponseFactory($factory, $factory)))->process($request, $handler);
        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('error="invalid_request"', $response->getHeaderLine('WWW-Authenticate'));
        self::assertSame('', (string) $response->getBody());
    }

    public static function unsupportedTransports(): iterable
    {
        yield ['https://resource.test/mcp?access_token=secret', [], []];
        yield ['https://resource.test/mcp?a=1&%61ccess_token=secret', ['Authorization' => 'Bearer header-token'], []];
        yield ['https://resource.test/mcp?access_token%5B%5D=secret', [], []];
        yield ['https://resource.test/mcp', [], ['access_token' => 'secret']];
        yield ['https://resource.test/mcp', ['Content-Type' => 'Application/X-WWW-Form-Urlencoded; charset=UTF-8'], []];
    }

    public function test_that_success_returns_the_downstream_response_unchanged_after_claim_handoff(): void
    {
        $factory = new HttpFactory();
        $validator = $this->createMock(OAuthTokenValidator::class);
        $validator->expects(self::once())->method('validate')->willReturn(OAuthFixture::claims());
        $handedOff = false;
        $handoff = $this->createMock(OAuthClaimsHandoff::class);
        $handoff->expects(self::once())->method('accept')->willReturnCallback(function () use (&$handedOff): void { $handedOff = true; });
        $diagnostics = $this->createMock(OAuthDiagnostics::class);
        $diagnostics->expects(self::never())->method('record');
        $request = new ServerRequest('POST', 'https://resource.test/mcp?q=ordinary', ['Authorization' => 'Bearer token', 'Content-Type' => 'application/json'], '{}');
        $expected = new Response(202, ['X-Consumer' => 'yes'], 'consumer body');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturnCallback(function () use (&$handedOff, $expected): Response {
            self::assertTrue($handedOff);
            return $expected;
        });
        $server = new OAuthResourceServer(OAuthFixture::requirements(), $validator, $handoff, $diagnostics);
        self::assertSame($expected, (new OAuthMiddleware($server, OAuthScopeSet::fromArray(['mcp:read']), new OAuthResponseFactory($factory, $factory)))->process($request, $handler));
    }

    public function test_that_downstream_failure_is_not_reclassified_or_logged_twice(): void
    {
        $factory = new HttpFactory();
        $validator = $this->createMock(OAuthTokenValidator::class);
        $validator->expects(self::once())->method('validate')->willReturn(OAuthFixture::claims());
        $handoff = $this->createMock(OAuthClaimsHandoff::class);
        $handoff->expects(self::once())->method('accept');
        $diagnostics = $this->createMock(OAuthDiagnostics::class);
        $diagnostics->expects(self::never())->method('record');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willThrowException(new RuntimeException('downstream owns diagnostics'));
        $server = new OAuthResourceServer(OAuthFixture::requirements(), $validator, $handoff, $diagnostics);
        $this->expectExceptionMessage('downstream owns diagnostics');
        (new OAuthMiddleware($server, OAuthScopeSet::fromArray([]), new OAuthResponseFactory($factory, $factory)))->process(new ServerRequest('POST', OAuthFixture::RESOURCE, ['Authorization' => 'Bearer token']), $handler);
    }
}
