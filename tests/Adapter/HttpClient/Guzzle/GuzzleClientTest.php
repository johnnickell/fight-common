<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\HttpClient\Guzzle;

use Fight\Common\Adapter\HttpClient\Guzzle\GuzzleClient;
use Fight\Common\Adapter\HttpClient\Guzzle\GuzzlePromise;
use Fight\Common\Application\HttpClient\Exception\TransferException;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

#[CoversClass(GuzzleClient::class)]
class GuzzleClientTest extends UnitTestCase
{
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new Request('GET', 'https://example.com');
    }

    public function test_that_send_async_returns_guzzle_promise(): void
    {
        $response = new Response(200);

        /** @var MockInterface|ClientInterface $guzzle */
        $guzzle = $this->mock(ClientInterface::class);
        $guzzle->shouldReceive('sendAsync')
            ->with($this->request, [])
            ->andReturn(new FulfilledPromise($response));

        $client = new GuzzleClient($guzzle);
        $promise = $client->sendAsync($this->request);

        self::assertInstanceOf(GuzzlePromise::class, $promise);
    }

    public function test_that_send_returns_response_for_fulfilled_promise(): void
    {
        $response = new Response(200, [], 'body');

        /** @var MockInterface|ClientInterface $guzzle */
        $guzzle = $this->mock(ClientInterface::class);
        $guzzle->shouldReceive('sendAsync')
            ->andReturn(new FulfilledPromise($response));

        $client = new GuzzleClient($guzzle);
        $result = $client->send($this->request);

        self::assertInstanceOf(ResponseInterface::class, $result);
        self::assertSame(200, $result->getStatusCode());
    }

    public function test_that_send_rethrows_fight_exception_directly(): void
    {
        $fightEx = new TransferException('fight error');

        /** @var MockInterface|ClientInterface $guzzle */
        $guzzle = $this->mock(ClientInterface::class);
        $guzzle->shouldReceive('sendAsync')
            ->andReturn(new RejectedPromise($fightEx));

        $client = new GuzzleClient($guzzle);

        $this->expectException(TransferException::class);
        $client->send($this->request);
    }

    public function test_that_send_wraps_non_fight_exception_in_transfer_exception(): void
    {
        $genericEx = new RuntimeException('something failed');

        /** @var MockInterface|ClientInterface $guzzle */
        $guzzle = $this->mock(ClientInterface::class);
        $guzzle->shouldReceive('sendAsync')
            ->andReturn(new RejectedPromise($genericEx));

        $client = new GuzzleClient($guzzle);

        $this->expectException(TransferException::class);
        $client->send($this->request);
    }
}
