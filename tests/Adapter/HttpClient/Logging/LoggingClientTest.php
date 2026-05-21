<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\HttpClient\Logging;

use Fight\Common\Adapter\HttpClient\Logging\LoggingHttpClient;
use Fight\Common\Application\HttpClient\Message\Promise;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;

#[CoversClass(LoggingHttpClient::class)]
class LoggingClientTest extends UnitTestCase
{
    private RequestInterface $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new Request('GET', 'https://example.com/api', [], 'request-body');
    }

    public function test_that_send_async_logs_request_and_returns_promise(): void
    {
        $response = new Response(200, [], 'response-body');

        /** @var MockInterface|Promise $promise */
        $promise = $this->mock(Promise::class);
        $promise->shouldReceive('then')->andReturnSelf();

        /** @var MockInterface|HttpClient $innerClient */
        $innerClient = $this->mock(HttpClient::class);
        $innerClient->shouldReceive('sendAsync')->andReturn($promise);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')
            ->with(LogLevel::DEBUG, '[HTTP]: Outgoing HTTP Request', \Mockery::type('array'))
            ->once();

        $client = new LoggingHttpClient($innerClient, $logger);
        $result = $client->sendAsync($this->request);

        self::assertInstanceOf(Promise::class, $result);
    }

    public function test_that_send_async_uses_custom_log_level(): void
    {
        /** @var MockInterface|Promise $promise */
        $promise = $this->mock(Promise::class);
        $promise->shouldReceive('then')->andReturnSelf();

        /** @var MockInterface|HttpClient $innerClient */
        $innerClient = $this->mock(HttpClient::class);
        $innerClient->shouldReceive('sendAsync')->andReturn($promise);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')
            ->with(LogLevel::INFO, \Mockery::type('string'), \Mockery::type('array'))
            ->once();

        $client = new LoggingHttpClient($innerClient, $logger, LogLevel::INFO);
        $client->sendAsync($this->request);
    }

    public function test_that_fulfilled_callback_logs_response_and_rewinds_stream(): void
    {
        /** @var MockInterface|StreamInterface $stream */
        $stream = $this->mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('response body');
        $stream->shouldReceive('rewind')->once();

        /** @var MockInterface|ResponseInterface $response */
        $response = $this->mock(ResponseInterface::class);
        $response->shouldReceive('getBody')->andReturn($stream);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getReasonPhrase')->andReturn('OK');
        $response->shouldReceive('getProtocolVersion')->andReturn('1.1');
        $response->shouldReceive('getHeaders')->andReturn([]);

        $capturedFulfilled = null;

        /** @var MockInterface|Promise $innerPromise */
        $innerPromise = $this->mock(Promise::class);
        $innerPromise->shouldReceive('then')
            ->withArgs(function (callable $fulfilled, callable $rejected) use (&$capturedFulfilled) {
                $capturedFulfilled = $fulfilled;
                return true;
            })
            ->andReturnSelf();

        /** @var MockInterface|HttpClient $innerClient */
        $innerClient = $this->mock(HttpClient::class);
        $innerClient->shouldReceive('sendAsync')->andReturn($innerPromise);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')->with(LogLevel::DEBUG, '[HTTP]: Outgoing HTTP Request', \Mockery::any());
        $logger->shouldReceive('log')
            ->with(LogLevel::DEBUG, '[HTTP]: Incoming HTTP Response', \Mockery::type('array'))
            ->once();

        $client = new LoggingHttpClient($innerClient, $logger);
        $client->sendAsync($this->request);

        $result = $capturedFulfilled($response);
        self::assertSame($response, $result);
    }

    public function test_that_rejected_callback_logs_exception_and_rethrows(): void
    {
        $exception = new RuntimeException('connection failed');
        $capturedRejected = null;

        /** @var MockInterface|Promise $innerPromise */
        $innerPromise = $this->mock(Promise::class);
        $innerPromise->shouldReceive('then')
            ->withArgs(function (callable $fulfilled, callable $rejected) use (&$capturedRejected) {
                $capturedRejected = $rejected;
                return true;
            })
            ->andReturnSelf();

        /** @var MockInterface|HttpClient $innerClient */
        $innerClient = $this->mock(HttpClient::class);
        $innerClient->shouldReceive('sendAsync')->andReturn($innerPromise);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')->with(LogLevel::DEBUG, '[HTTP]: Outgoing HTTP Request', \Mockery::any());
        $logger->shouldReceive('log')
            ->with(LogLevel::DEBUG, '[HTTP]: HTTP Error Exception', \Mockery::type('array'))
            ->once();

        $client = new LoggingHttpClient($innerClient, $logger);
        $client->sendAsync($this->request);

        $this->expectException(RuntimeException::class);
        $capturedRejected($exception);
    }

    public function test_that_send_delegates_to_send_async_and_returns_response(): void
    {
        $response = new Response(200);

        /** @var MockInterface|StreamInterface $stream */
        $stream = $this->mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('');
        $stream->shouldReceive('rewind');

        /** @var MockInterface|ResponseInterface $mockedResponse */
        $mockedResponse = $this->mock(ResponseInterface::class);
        $mockedResponse->shouldReceive('getBody')->andReturn($stream);
        $mockedResponse->shouldReceive('getStatusCode')->andReturn(200);
        $mockedResponse->shouldReceive('getReasonPhrase')->andReturn('OK');
        $mockedResponse->shouldReceive('getProtocolVersion')->andReturn('1.1');
        $mockedResponse->shouldReceive('getHeaders')->andReturn([]);

        // Use the real GuzzlePromise chain via a concrete inner client
        $capturedFulfilled = null;

        /** @var MockInterface|Promise $innerPromise */
        $innerPromise = $this->mock(Promise::class);
        $innerPromise->shouldReceive('then')
            ->withArgs(function (callable $f, callable $r) use (&$capturedFulfilled) {
                $capturedFulfilled = $f;
                return true;
            })
            ->andReturnSelf();
        $innerPromise->shouldReceive('wait')->andReturnUsing(function () use (&$capturedFulfilled, $mockedResponse) {
            $capturedFulfilled($mockedResponse);
        });
        $innerPromise->shouldReceive('getState')->andReturn(Promise::FULFILLED);
        $innerPromise->shouldReceive('getResponse')->andReturn($mockedResponse);

        /** @var MockInterface|HttpClient $innerClient */
        $innerClient = $this->mock(HttpClient::class);
        $innerClient->shouldReceive('sendAsync')->andReturn($innerPromise);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')->withAnyArgs();

        $client = new LoggingHttpClient($innerClient, $logger);
        $result = $client->send($this->request);

        self::assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_that_send_throws_when_promise_is_rejected(): void
    {
        $exception = new RuntimeException('failed');

        /** @var MockInterface|Promise $innerPromise */
        $innerPromise = $this->mock(Promise::class);
        $innerPromise->shouldReceive('then')->andReturnSelf();
        $innerPromise->shouldReceive('wait');
        $innerPromise->shouldReceive('getState')->andReturn(Promise::REJECTED);
        $innerPromise->shouldReceive('getException')->andReturn($exception);

        /** @var MockInterface|HttpClient $innerClient */
        $innerClient = $this->mock(HttpClient::class);
        $innerClient->shouldReceive('sendAsync')->andReturn($innerPromise);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')->withAnyArgs();

        $client = new LoggingHttpClient($innerClient, $logger);

        $this->expectException(RuntimeException::class);
        $client->send($this->request);
    }
}
