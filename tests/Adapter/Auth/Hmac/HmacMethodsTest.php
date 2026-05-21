<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Auth\Hmac;

use Fight\Common\Adapter\Auth\Hmac\HmacAuthenticator;
use Fight\Common\Adapter\Auth\Hmac\HmacMethods;
use Fight\Common\Adapter\Auth\Hmac\HmacRequestService;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\UriInterface;

#[CoversClass(HmacAuthenticator::class)]
#[CoversClass(HmacRequestService::class)]
class HmacMethodsTest extends UnitTestCase
{
    private function makeSubject(): object
    {
        return new class(hex2bin('deadbeef1234567890abcdef12345678')) {
            use HmacMethods;

            public function __construct(private readonly string $secret) {}

            protected function getSecret(): string
            {
                return $this->secret;
            }

            public function exposedNormalizeUri(UriInterface $uri): UriInterface
            {
                return $this->normalizeUri($uri);
            }

            public function exposedCreateCanonicalRequestString(
                string $method,
                string $authority,
                string $path,
                string $query,
                array $headers
            ): string {
                return $this->createCanonicalRequestString($method, $authority, $path, $query, $headers);
            }

            public function exposedCreateSignature(string $canonicalRequest, int $timestamp): string
            {
                return $this->createSignature($canonicalRequest, $timestamp);
            }
        };
    }

    public function test_that_normalize_uri_sorts_query_params_alphabetically(): void
    {
        $subject = $this->makeSubject();

        /** @var MockInterface|UriInterface $normalizedUri */
        $normalizedUri = $this->mock(UriInterface::class);

        /** @var MockInterface|UriInterface $uri */
        $uri = $this->mock(UriInterface::class);
        $uri->shouldReceive('getQuery')->andReturn('z=last&a=first&m=middle');
        $uri->shouldReceive('withQuery')->with('a=first&m=middle&z=last')->once()->andReturn($normalizedUri);

        $result = $subject->exposedNormalizeUri($uri);

        self::assertSame($normalizedUri, $result);
    }

    public function test_that_normalize_uri_returns_uri_unchanged_when_query_string_is_empty(): void
    {
        $subject = $this->makeSubject();

        /** @var MockInterface|UriInterface $uri */
        $uri = $this->mock(UriInterface::class);
        $uri->shouldReceive('getQuery')->andReturn('');

        $result = $subject->exposedNormalizeUri($uri);

        self::assertSame($uri, $result);
    }

    public function test_that_normalize_uri_with_single_query_param_produces_unchanged_query(): void
    {
        $subject = $this->makeSubject();

        /** @var MockInterface|UriInterface $normalizedUri */
        $normalizedUri = $this->mock(UriInterface::class);

        /** @var MockInterface|UriInterface $uri */
        $uri = $this->mock(UriInterface::class);
        $uri->shouldReceive('getQuery')->andReturn('foo=bar');
        $uri->shouldReceive('withQuery')->with('foo=bar')->once()->andReturn($normalizedUri);

        $result = $subject->exposedNormalizeUri($uri);

        self::assertSame($normalizedUri, $result);
    }

    public function test_that_create_canonical_request_string_produces_expected_format(): void
    {
        $subject = $this->makeSubject();

        $headers = [
            'X-Timestamp' => 1000,
            'X-Nonce' => 'abc123',
        ];

        $result = $subject->exposedCreateCanonicalRequestString(
            'POST',
            'example.com',
            '/api/resource',
            'key=val',
            $headers
        );

        $expected = "POST example.com/api/resource?key=val\nx-timestamp:1000\nx-nonce:abc123\n";
        self::assertSame($expected, $result);
    }

    public function test_that_create_canonical_request_string_uses_root_path_when_path_is_empty(): void
    {
        $subject = $this->makeSubject();

        $result = $subject->exposedCreateCanonicalRequestString('GET', 'example.com', '', '', []);

        self::assertStringContainsString('GET example.com/', $result);
    }

    public function test_that_create_canonical_request_string_omits_query_separator_when_query_is_empty(): void
    {
        $subject = $this->makeSubject();

        $result = $subject->exposedCreateCanonicalRequestString('GET', 'example.com', '/path', '', []);

        self::assertStringNotContainsString('?', $result);
    }

    public function test_that_create_signature_returns_consistent_hmac_for_same_inputs(): void
    {
        $subject = $this->makeSubject();
        $canonical = 'GET example.com/api/test' . "\n" . 'x-timestamp:1000' . "\n";
        $timestamp = 1000;

        $signature1 = $subject->exposedCreateSignature($canonical, $timestamp);
        $signature2 = $subject->exposedCreateSignature($canonical, $timestamp);

        self::assertSame($signature1, $signature2);
        self::assertNotEmpty($signature1);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $signature1);
    }

    public function test_that_create_signature_returns_different_value_for_different_inputs(): void
    {
        $subject = $this->makeSubject();
        $canonical = 'GET example.com/api/test' . "\n";

        $sig1 = $subject->exposedCreateSignature($canonical, 1000);
        $sig2 = $subject->exposedCreateSignature($canonical, 9999);

        self::assertNotSame($sig1, $sig2);
    }
}
