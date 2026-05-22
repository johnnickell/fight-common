<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Auth\Hmac;

use Fight\Common\Adapter\Auth\Hmac\HmacAuthenticator;
use Fight\Common\Adapter\Auth\Hmac\HmacMethods;
use Fight\Common\Application\Auth\Exception\AuthException;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

#[CoversClass(HmacAuthenticator::class)]
class HmacAuthenticatorTest extends UnitTestCase
{
    private const string PUBLIC_KEY = 'test-public-key';

    private const array REQUIRED_HEADERS = ['Authorization', 'Credential', 'Signature', 'X-Timestamp', 'X-Nonce'];

    private string $privateKeyHex;

    protected function setUp(): void
    {
        parent::setUp();
        $this->privateKeyHex = bin2hex(random_bytes(16));
    }

    private function computeSignature(
        string $privateKeyHex,
        string $method,
        string $authority,
        string $path,
        string $query,
        array $headers,
        int $timestamp
    ): string {
        $signer = new readonly class(hex2bin($privateKeyHex)) {
            use HmacMethods;

            public function __construct(private string $secret) {}

            protected function getSecret(): string
            {
                return $this->secret;
            }

            public function sign(
                string $method,
                string $authority,
                string $path,
                string $query,
                array $headers,
                int $timestamp
            ): string {
                $canonical = $this->createCanonicalRequestString($method, $authority, $path, $query, $headers);
                return $this->createSignature($canonical, $timestamp);
            }
        };

        return $signer->sign($method, $authority, $path, $query, $headers, $timestamp);
    }

    private function mockUri(string $authority = 'example.com', string $path = '/api', string $query = ''): UriInterface
    {
        /** @var MockInterface|UriInterface $uri */
        $uri = $this->mock(UriInterface::class);
        $uri->shouldReceive('getQuery')->andReturn($query);
        $uri->shouldReceive('getAuthority')->andReturn($authority);
        $uri->shouldReceive('getPath')->andReturn($path);

        return $uri;
    }

    private function mockEmptyBody(): StreamInterface
    {
        /** @var MockInterface|StreamInterface $body */
        $body = $this->mock(StreamInterface::class);
        $body->shouldReceive('__toString')->andReturn('');

        return $body;
    }

    public function test_that_validate_returns_true_for_valid_signed_request(): void
    {
        $timestamp = time();
        $nonce = 'test-nonce';
        $method = 'GET';
        $authority = 'example.com';
        $path = '/api';

        $hmacHeaders = ['X-Timestamp' => $timestamp, 'X-Nonce' => $nonce];
        $signature = $this->computeSignature($this->privateKeyHex, $method, $authority, $path, '', $hmacHeaders, $timestamp);

        $uri = $this->mockUri($authority, $path);
        $body = $this->mockEmptyBody();

        /** @var MockInterface|ServerRequestInterface $request */
        $request = $this->mock(ServerRequestInterface::class);
        foreach (self::REQUIRED_HEADERS as $header) {
            $request->shouldReceive('hasHeader')->with($header)->andReturn(true);
        }

        $request->shouldReceive('hasHeader')->with('X-Content-SHA256')->andReturn(false);
        $request->shouldReceive('getServerParams')->andReturn(['REQUEST_TIME' => $timestamp]);
        $request->shouldReceive('getHeaderLine')->with('X-Timestamp')->andReturn((string) $timestamp);
        $request->shouldReceive('getHeaderLine')->with('Credential')->andReturn(self::PUBLIC_KEY);
        $request->shouldReceive('getHeaderLine')->with('X-Nonce')->andReturn($nonce);
        $request->shouldReceive('getHeaderLine')->with('Signature')->andReturn($signature);
        $request->shouldReceive('getBody')->andReturn($body);
        $request->shouldReceive('getMethod')->andReturn($method);
        $request->shouldReceive('getUri')->andReturn($uri);

        $authenticator = new HmacAuthenticator(self::PUBLIC_KEY, $this->privateKeyHex, 300);

        self::assertTrue($authenticator->validate($request));
    }

    public function test_that_validate_returns_false_when_signature_does_not_match(): void
    {
        $timestamp = time();
        $nonce = 'test-nonce';

        $uri = $this->mockUri();
        $body = $this->mockEmptyBody();

        /** @var MockInterface|ServerRequestInterface $request */
        $request = $this->mock(ServerRequestInterface::class);
        foreach (self::REQUIRED_HEADERS as $header) {
            $request->shouldReceive('hasHeader')->with($header)->andReturn(true);
        }

        $request->shouldReceive('hasHeader')->with('X-Content-SHA256')->andReturn(false);
        $request->shouldReceive('getServerParams')->andReturn(['REQUEST_TIME' => $timestamp]);
        $request->shouldReceive('getHeaderLine')->with('X-Timestamp')->andReturn((string) $timestamp);
        $request->shouldReceive('getHeaderLine')->with('Credential')->andReturn(self::PUBLIC_KEY);
        $request->shouldReceive('getHeaderLine')->with('X-Nonce')->andReturn($nonce);
        $request->shouldReceive('getHeaderLine')->with('Signature')->andReturn('invalid-signature');
        $request->shouldReceive('getBody')->andReturn($body);
        $request->shouldReceive('getMethod')->andReturn('GET');
        $request->shouldReceive('getUri')->andReturn($uri);

        $authenticator = new HmacAuthenticator(self::PUBLIC_KEY, $this->privateKeyHex, 300);

        self::assertFalse($authenticator->validate($request));
    }

    public function test_that_validate_throws_when_required_header_is_missing(): void
    {
        /** @var MockInterface|ServerRequestInterface $request */
        $request = $this->mock(ServerRequestInterface::class);
        $request->shouldReceive('getServerParams')->andReturn([]);
        // Return false for every hasHeader call so the loop throws on the very first header
        $request->shouldReceive('hasHeader')->andReturn(false);

        $authenticator = new HmacAuthenticator(self::PUBLIC_KEY, $this->privateKeyHex, 300);

        $this->expectException(AuthException::class);
        $authenticator->validate($request);
    }

    public function test_that_validate_throws_when_signature_header_is_missing(): void
    {
        /** @var MockInterface|ServerRequestInterface $request */
        $request = $this->mock(ServerRequestInterface::class);
        $request->shouldReceive('getServerParams')->andReturn([]);
        // Return true for all headers except Signature so the loop throws exactly there
        $request->shouldReceive('hasHeader')->andReturnUsing(
            fn(string $header): bool => $header !== 'Signature'
        );

        $authenticator = new HmacAuthenticator(self::PUBLIC_KEY, $this->privateKeyHex, 300);

        $this->expectException(AuthException::class);
        $authenticator->validate($request);
    }

    public function test_that_validate_throws_when_credential_does_not_match_public_key(): void
    {
        $timestamp = time();

        /** @var MockInterface|ServerRequestInterface $request */
        $request = $this->mock(ServerRequestInterface::class);
        foreach (self::REQUIRED_HEADERS as $header) {
            $request->shouldReceive('hasHeader')->with($header)->andReturn(true);
        }

        $request->shouldReceive('getServerParams')->andReturn(['REQUEST_TIME' => $timestamp]);
        $request->shouldReceive('getHeaderLine')->with('X-Timestamp')->andReturn((string) $timestamp);
        $request->shouldReceive('getHeaderLine')->with('Credential')->andReturn('wrong-credential');

        $authenticator = new HmacAuthenticator(self::PUBLIC_KEY, $this->privateKeyHex, 300);

        $this->expectException(AuthException::class);
        $authenticator->validate($request);
    }

    public function test_that_validate_throws_when_body_is_present_without_content_hash_header(): void
    {
        $timestamp = time();

        /** @var MockInterface|StreamInterface $body */
        $body = $this->mock(StreamInterface::class);
        $body->shouldReceive('__toString')->andReturn('request-body');

        /** @var MockInterface|ServerRequestInterface $request */
        $request = $this->mock(ServerRequestInterface::class);
        foreach (self::REQUIRED_HEADERS as $header) {
            $request->shouldReceive('hasHeader')->with($header)->andReturn(true);
        }

        $request->shouldReceive('hasHeader')->with('X-Content-SHA256')->andReturn(false);
        $request->shouldReceive('getServerParams')->andReturn(['REQUEST_TIME' => $timestamp]);
        $request->shouldReceive('getHeaderLine')->with('X-Timestamp')->andReturn((string) $timestamp);
        $request->shouldReceive('getHeaderLine')->with('Credential')->andReturn(self::PUBLIC_KEY);
        $request->shouldReceive('getBody')->andReturn($body);

        $authenticator = new HmacAuthenticator(self::PUBLIC_KEY, $this->privateKeyHex, 300);

        $this->expectException(AuthException::class);
        $authenticator->validate($request);
    }

    public function test_that_validate_throws_when_body_content_hash_does_not_match(): void
    {
        $timestamp = time();

        /** @var MockInterface|StreamInterface $body */
        $body = $this->mock(StreamInterface::class);
        $body->shouldReceive('__toString')->andReturn('request-body');

        /** @var MockInterface|ServerRequestInterface $request */
        $request = $this->mock(ServerRequestInterface::class);
        foreach (self::REQUIRED_HEADERS as $header) {
            $request->shouldReceive('hasHeader')->with($header)->andReturn(true);
        }

        $request->shouldReceive('hasHeader')->with('X-Content-SHA256')->andReturn(true);
        $request->shouldReceive('getServerParams')->andReturn(['REQUEST_TIME' => $timestamp]);
        $request->shouldReceive('getHeaderLine')->with('X-Timestamp')->andReturn((string) $timestamp);
        $request->shouldReceive('getHeaderLine')->with('Credential')->andReturn(self::PUBLIC_KEY);
        $request->shouldReceive('getHeaderLine')->with('X-Content-SHA256')->andReturn('wrong-hash');
        $request->shouldReceive('getBody')->andReturn($body);

        $authenticator = new HmacAuthenticator(self::PUBLIC_KEY, $this->privateKeyHex, 300);

        $this->expectException(AuthException::class);
        $authenticator->validate($request);
    }

    public function test_that_validate_returns_false_when_signature_does_not_match_for_request_with_body(): void
    {
        $timestamp = time();
        $content = 'request-body';
        $contentHash = hash('sha256', $content);
        $nonce = 'test-nonce';

        /** @var MockInterface|StreamInterface $body */
        $body = $this->mock(StreamInterface::class);
        $body->shouldReceive('__toString')->andReturn($content);

        $uri = $this->mockUri();

        /** @var MockInterface|ServerRequestInterface $request */
        $request = $this->mock(ServerRequestInterface::class);
        foreach (self::REQUIRED_HEADERS as $header) {
            $request->shouldReceive('hasHeader')->with($header)->andReturn(true);
        }

        // X-Content-SHA256 is present and correct so body validation passes,
        // and it is included in the canonical request (lines 92–96)
        $request->shouldReceive('hasHeader')->with('X-Content-SHA256')->andReturn(true);
        $request->shouldReceive('getServerParams')->andReturn(['REQUEST_TIME' => $timestamp]);
        $request->shouldReceive('getHeaderLine')->with('X-Timestamp')->andReturn((string) $timestamp);
        $request->shouldReceive('getHeaderLine')->with('Credential')->andReturn(self::PUBLIC_KEY);
        $request->shouldReceive('getHeaderLine')->with('X-Content-SHA256')->andReturn($contentHash);
        $request->shouldReceive('getHeaderLine')->with('X-Nonce')->andReturn($nonce);
        $request->shouldReceive('getHeaderLine')->with('Signature')->andReturn('wrong-signature');
        $request->shouldReceive('getBody')->andReturn($body);
        $request->shouldReceive('getMethod')->andReturn('POST');
        $request->shouldReceive('getUri')->andReturn($uri);

        $authenticator = new HmacAuthenticator(self::PUBLIC_KEY, $this->privateKeyHex, 300);

        self::assertFalse($authenticator->validate($request));
    }

    public function test_that_validate_throws_when_timestamp_is_expired(): void
    {
        $expiredTimestamp = time() - 600; // 600 seconds old, tolerance is 300

        /** @var MockInterface|ServerRequestInterface $request */
        $request = $this->mock(ServerRequestInterface::class);
        foreach (self::REQUIRED_HEADERS as $header) {
            $request->shouldReceive('hasHeader')->with($header)->andReturn(true);
        }

        $request->shouldReceive('getServerParams')->andReturn([]);
        $request->shouldReceive('getHeaderLine')->with('X-Timestamp')->andReturn((string) $expiredTimestamp);

        $authenticator = new HmacAuthenticator(self::PUBLIC_KEY, $this->privateKeyHex, 300);

        $this->expectException(AuthException::class);
        $authenticator->validate($request);
    }
}
