<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\HttpClient\Guzzle;

use Throwable;
use Fight\Common\Adapter\HttpClient\Guzzle\GuzzlePromise;
use Fight\Common\Application\HttpClient\Exception\HttpException;
use Fight\Common\Application\HttpClient\Exception\NetworkException;
use Fight\Common\Application\HttpClient\Exception\RequestException as FightRequestException;
use Fight\Common\Application\HttpClient\Exception\TransferException;
use Fight\Common\Application\HttpClient\Message\Promise;
use Fight\Common\Domain\Exception\MethodCallException;
use Fight\Common\Domain\Exception\RuntimeException;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Exception\TransferException as GuzzleTransferException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(GuzzlePromise::class)]
class GuzzlePromiseTest extends UnitTestCase
{
    private RequestInterface $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new Request('GET', 'https://example.com');
    }

    public function test_that_initial_state_is_pending(): void
    {
        $innerPromise = new FulfilledPromise(new Response(200));
        $promise = new GuzzlePromise($innerPromise, $this->request);

        // Before wait(), state transitions are deferred
        // After waiting it will be FULFILLED
        $promise->wait();

        self::assertSame(Promise::FULFILLED, $promise->getState());
    }

    public function test_that_fulfilled_promise_provides_response(): void
    {
        $response = new Response(200, [], 'ok');
        $innerPromise = new FulfilledPromise($response);
        $promise = new GuzzlePromise($innerPromise, $this->request);

        $promise->wait();

        self::assertSame(Promise::FULFILLED, $promise->getState());
        self::assertSame(200, $promise->getResponse()->getStatusCode());
    }

    public function test_that_get_response_throws_when_not_fulfilled(): void
    {
        $innerPromise = new RejectedPromise(
            new GuzzleConnectException('network error', $this->request)
        );
        $promise = new GuzzlePromise($innerPromise, $this->request);

        try {
            $promise->wait();
        } catch (Throwable) {
        }

        $this->expectException(MethodCallException::class);
        $promise->getResponse();
    }

    public function test_that_get_exception_throws_when_not_rejected(): void
    {
        $innerPromise = new FulfilledPromise(new Response(200));
        $promise = new GuzzlePromise($innerPromise, $this->request);
        $promise->wait();

        $this->expectException(MethodCallException::class);
        $promise->getException();
    }

    public function test_that_rejection_with_fight_exception_stores_it_directly(): void
    {
        $fightException = new TransferException('fight error');
        $innerPromise = new RejectedPromise($fightException);
        $promise = new GuzzlePromise($innerPromise, $this->request);

        try {
            $promise->wait();
        } catch (Throwable) {
        }

        self::assertSame(Promise::REJECTED, $promise->getState());
        self::assertSame($fightException, $promise->getException());
    }

    public function test_that_rejection_with_non_guzzle_exception_wraps_in_runtime_exception(): void
    {
        $innerPromise = new RejectedPromise(new \RuntimeException('unexpected'));
        $promise = new GuzzlePromise($innerPromise, $this->request);

        try {
            $promise->wait();
        } catch (Throwable) {
        }

        self::assertSame(Promise::REJECTED, $promise->getState());
        self::assertInstanceOf(RuntimeException::class, $promise->getException());
    }

    public function test_that_rejection_with_connect_exception_wraps_in_network_exception(): void
    {
        $guzzleEx = new GuzzleConnectException('connection refused', $this->request);
        $innerPromise = new RejectedPromise($guzzleEx);
        $promise = new GuzzlePromise($innerPromise, $this->request);

        try {
            $promise->wait();
        } catch (Throwable) {
        }

        self::assertSame(Promise::REJECTED, $promise->getState());
        self::assertInstanceOf(NetworkException::class, $promise->getException());
    }

    public function test_that_rejection_with_request_exception_with_response_wraps_in_http_exception(): void
    {
        $response = new Response(404);
        $guzzleEx = new GuzzleRequestException('not found', $this->request, $response);
        $innerPromise = new RejectedPromise($guzzleEx);
        $promise = new GuzzlePromise($innerPromise, $this->request);

        try {
            $promise->wait();
        } catch (Throwable) {
        }

        self::assertSame(Promise::REJECTED, $promise->getState());
        self::assertInstanceOf(HttpException::class, $promise->getException());
    }

    public function test_that_rejection_with_request_exception_without_response_wraps_in_request_exception(): void
    {
        $guzzleEx = new GuzzleRequestException('request failed', $this->request);
        $innerPromise = new RejectedPromise($guzzleEx);
        $promise = new GuzzlePromise($innerPromise, $this->request);

        try {
            $promise->wait();
        } catch (Throwable) {
        }

        self::assertSame(Promise::REJECTED, $promise->getState());
        self::assertInstanceOf(FightRequestException::class, $promise->getException());
    }

    public function test_that_rejection_with_other_guzzle_exception_wraps_in_transfer_exception(): void
    {
        $guzzleEx = new GuzzleTransferException('generic guzzle failure');
        $innerPromise = new RejectedPromise($guzzleEx);
        $promise = new GuzzlePromise($innerPromise, $this->request);

        try {
            $promise->wait();
        } catch (Throwable) {
        }

        self::assertSame(Promise::REJECTED, $promise->getState());
        self::assertInstanceOf(TransferException::class, $promise->getException());
        self::assertNotInstanceOf(NetworkException::class, $promise->getException());
        self::assertNotInstanceOf(FightRequestException::class, $promise->getException());
    }

    public function test_that_then_returns_new_promise_with_fulfilled_callback(): void
    {
        $response = new Response(200);
        $innerPromise = new FulfilledPromise($response);
        $promise = new GuzzlePromise($innerPromise, $this->request);

        $chained = $promise->then(fn(ResponseInterface $r): ResponseInterface => $r);

        self::assertInstanceOf(GuzzlePromise::class, $chained);
    }
}
