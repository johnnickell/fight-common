<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Auth\Hmac;

use Fight\Common\Adapter\Auth\Hmac\HmacAuthenticator;
use Fight\Common\Adapter\Auth\Hmac\HmacRequestService;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\ServerRequest;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

#[CoversClass(HmacRequestService::class)]
#[UsesClass(HmacAuthenticator::class)]
class HmacRequestServiceTest extends UnitTestCase
{
    private const string PUBLIC_KEY = 'test-public-key';

    private string $privateKeyHex;

    protected function setUp(): void
    {
        parent::setUp();
        $this->privateKeyHex = bin2hex(random_bytes(16));
    }

    public function test_that_sign_request_adds_expected_authorization_headers(): void
    {
        /** @var MockInterface|UriInterface $uri */
        $uri = $this->mock(UriInterface::class);
        $uri->shouldReceive('getQuery')->andReturn('');
        $uri->shouldReceive('getAuthority')->andReturn('example.com');
        $uri->shouldReceive('getPath')->andReturn('/api/test');

        /** @var MockInterface|StreamInterface $body */
        $body = $this->mock(StreamInterface::class);
        $body->shouldReceive('__toString')->andReturn('');

        /** @var MockInterface|RequestInterface $request */
        $request = $this->mock(RequestInterface::class);
        $request->shouldReceive('getMethod')->andReturn('GET');
        $request->shouldReceive('getUri')->andReturn($uri);
        $request->shouldReceive('getBody')->andReturn($body);
        $request->shouldReceive('withUri')->with($uri)->andReturnSelf();
        $request->shouldReceive('withHeader')->with('Authorization', 'HMAC-SHA256')->once()->andReturnSelf();
        $request->shouldReceive('withHeader')->with('Credential', self::PUBLIC_KEY)->once()->andReturnSelf();
        $request->shouldReceive('withHeader')->with('Signature', Mockery::type('string'))->once()->andReturnSelf();
        $request->shouldReceive('withHeader')->with('X-Nonce', Mockery::type('string'))->once()->andReturnSelf();
        $request->shouldReceive('withHeader')->with('X-Timestamp', Mockery::type('string'))->once()->andReturnSelf();

        $service = new HmacRequestService(self::PUBLIC_KEY, $this->privateKeyHex);
        $result = $service->signRequest($request);

        self::assertSame($request, $result);
    }

    public function test_that_sign_request_adds_content_hash_header_when_request_has_a_body(): void
    {
        $content = 'request-body-content';
        $expectedContentHash = hash('sha256', $content);

        /** @var MockInterface|UriInterface $uri */
        $uri = $this->mock(UriInterface::class);
        $uri->shouldReceive('getQuery')->andReturn('');
        $uri->shouldReceive('getAuthority')->andReturn('example.com');
        $uri->shouldReceive('getPath')->andReturn('/api/resource');

        /** @var MockInterface|StreamInterface $body */
        $body = $this->mock(StreamInterface::class);
        $body->shouldReceive('__toString')->andReturn($content);

        /** @var MockInterface|RequestInterface $request */
        $request = $this->mock(RequestInterface::class);
        $request->shouldReceive('getMethod')->andReturn('POST');
        $request->shouldReceive('getUri')->andReturn($uri);
        $request->shouldReceive('getBody')->andReturn($body);
        $request->shouldReceive('withUri')->with($uri)->andReturnSelf();
        $request->shouldReceive('withHeader')->with('Authorization', 'HMAC-SHA256')->once()->andReturnSelf();
        $request->shouldReceive('withHeader')->with('Credential', self::PUBLIC_KEY)->once()->andReturnSelf();
        $request->shouldReceive('withHeader')->with('Signature', Mockery::type('string'))->once()->andReturnSelf();
        $request->shouldReceive('withHeader')->with('X-Content-SHA256', $expectedContentHash)->once()->andReturnSelf();
        $request->shouldReceive('withHeader')->with('X-Nonce', Mockery::type('string'))->once()->andReturnSelf();
        $request->shouldReceive('withHeader')->with('X-Timestamp', Mockery::type('string'))->once()->andReturnSelf();

        $service = new HmacRequestService(self::PUBLIC_KEY, $this->privateKeyHex);
        $result = $service->signRequest($request);

        self::assertSame($request, $result);
    }

    public function test_that_sign_request_emits_valid_headers_through_a_real_psr_7_request(): void
    {
        $request = new ServerRequest('POST', 'https://example.com/api/resource?b=2&a=1', [], 'request-body');
        $service = new HmacRequestService(self::PUBLIC_KEY, $this->privateKeyHex);

        $result = $service->signRequest($request);

        self::assertInstanceOf(ServerRequest::class, $result);
        self::assertMatchesRegularExpression('/^\d+$/', $result->getHeaderLine('X-Timestamp'));
        self::assertSame('HMAC-SHA256', $result->getHeaderLine('Authorization'));
        self::assertSame(self::PUBLIC_KEY, $result->getHeaderLine('Credential'));
        self::assertNotSame('', $result->getHeaderLine('Signature'));
        self::assertNotSame('', $result->getHeaderLine('X-Nonce'));
        self::assertSame(hash('sha256', 'request-body'), $result->getHeaderLine('X-Content-SHA256'));
        self::assertSame('a=1&b=2', $result->getUri()->getQuery());

        $authenticator = new HmacAuthenticator(self::PUBLIC_KEY, $this->privateKeyHex, 60);
        self::assertTrue($authenticator->validate($result));
    }
}
