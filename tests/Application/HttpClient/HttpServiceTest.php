<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\HttpClient;

use Override;
use Fight\Common\Application\HttpClient\HttpService;
use Fight\Common\Application\HttpClient\Message\MessageFactory;
use Fight\Common\Application\HttpClient\Message\Promise;
use Fight\Common\Application\HttpClient\Message\StreamFactory;
use Fight\Common\Application\HttpClient\Message\UriFactory;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

#[CoversClass(HttpService::class)]
class HttpServiceTest extends UnitTestCase
{
    /** @var MockInterface|HttpClient */
    private $httpClient;
    /** @var MockInterface|MessageFactory */
    private $messageFactory;
    /** @var MockInterface|StreamFactory */
    private $streamFactory;
    /** @var MockInterface|UriFactory */
    private $uriFactory;
    private HttpService $service;

    #[Override]
    protected function setUp(): void
    {
        $this->httpClient = $this->mock(HttpClient::class);
        $this->messageFactory = $this->mock(MessageFactory::class);
        $this->streamFactory = $this->mock(StreamFactory::class);
        $this->uriFactory = $this->mock(UriFactory::class);
        $this->service = new HttpService(
            $this->httpClient,
            $this->messageFactory,
            $this->streamFactory,
            $this->uriFactory
        );
    }

    public function test_that_construction_creates_service_implementing_all_interfaces(): void
    {
        self::assertInstanceOf(HttpClient::class, $this->service);
        self::assertInstanceOf(MessageFactory::class, $this->service);
        self::assertInstanceOf(StreamFactory::class, $this->service);
        self::assertInstanceOf(UriFactory::class, $this->service);
    }

    public function test_that_send_delegates_to_http_client_and_returns_response(): void
    {
        /** @var MockInterface|RequestInterface $request */
        $request = $this->mock(RequestInterface::class);
        /** @var MockInterface|ResponseInterface $response */
        $response = $this->mock(ResponseInterface::class);

        $this->httpClient
            ->shouldReceive('send')
            ->once()
            ->with($request, [])
            ->andReturn($response);

        $result = $this->service->send($request);

        self::assertSame($response, $result);
    }

    public function test_that_send_async_delegates_to_http_client_and_returns_promise(): void
    {
        /** @var MockInterface|RequestInterface $request */
        $request = $this->mock(RequestInterface::class);
        /** @var MockInterface|Promise $promise */
        $promise = $this->mock(Promise::class);

        $this->httpClient
            ->shouldReceive('sendAsync')
            ->once()
            ->with($request, [])
            ->andReturn($promise);

        $result = $this->service->sendAsync($request);

        self::assertSame($promise, $result);
    }

    public function test_that_create_request_delegates_to_message_factory_and_returns_request(): void
    {
        /** @var MockInterface|RequestInterface $request */
        $request = $this->mock(RequestInterface::class);

        $this->messageFactory
            ->shouldReceive('createRequest')
            ->once()
            ->with('GET', 'https://example.com', [], null, '1.1')
            ->andReturn($request);

        $result = $this->service->createRequest('GET', 'https://example.com');

        self::assertSame($request, $result);
    }

    public function test_that_create_response_delegates_to_message_factory_and_returns_response(): void
    {
        /** @var MockInterface|ResponseInterface $response */
        $response = $this->mock(ResponseInterface::class);

        $this->messageFactory
            ->shouldReceive('createResponse')
            ->once()
            ->with(200, [], null, '1.1', null)
            ->andReturn($response);

        $result = $this->service->createResponse();

        self::assertSame($response, $result);
    }

    public function test_that_create_stream_delegates_to_stream_factory_and_returns_stream(): void
    {
        /** @var MockInterface|StreamInterface $stream */
        $stream = $this->mock(StreamInterface::class);

        $this->streamFactory
            ->shouldReceive('createStream')
            ->once()
            ->with(null)
            ->andReturn($stream);

        $result = $this->service->createStream();

        self::assertSame($stream, $result);
    }

    public function test_that_create_uri_delegates_to_uri_factory_and_returns_uri(): void
    {
        /** @var MockInterface|UriInterface $uri */
        $uri = $this->mock(UriInterface::class);

        $this->uriFactory
            ->shouldReceive('createUri')
            ->once()
            ->with('https://example.com')
            ->andReturn($uri);

        $result = $this->service->createUri('https://example.com');

        self::assertSame($uri, $result);
    }
}
