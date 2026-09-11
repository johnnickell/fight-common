<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\HttpClient\Psr18;

use Fight\Common\Adapter\HttpClient\Psr18\Psr18Client;
use Fight\Common\Adapter\HttpClient\Psr18\Exception\ClientException;
use Fight\Common\Adapter\HttpClient\Psr18\Exception\NetworkException;
use Fight\Common\Adapter\HttpClient\Psr18\Exception\RequestException;
use Fight\Common\Application\HttpClient\Exception\NetworkException as FightNetworkException;
use Fight\Common\Application\HttpClient\Exception\RequestException as FightRequestException;
use Fight\Common\Application\HttpClient\Exception\TransferException;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(Psr18Client::class)]
#[CoversClass(ClientException::class)]
#[CoversClass(NetworkException::class)]
#[CoversClass(RequestException::class)]
class Psr18ClientTest extends UnitTestCase
{
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new Request('GET', 'https://example.com');
    }

    public function test_that_send_request_returns_4xx_responses_normally(): void
    {
        $response = new Response(404);

        /** @var HttpClient&MockInterface $httpClient */
        $httpClient = $this->mock(HttpClient::class);
        $httpClient->shouldReceive('send')->with($this->request)->andReturn($response);

        $client = new Psr18Client($httpClient);
        $result = $client->sendRequest($this->request);

        self::assertSame($response, $result);
        self::assertSame(404, $result->getStatusCode());
    }

    public function test_that_send_request_returns_5xx_responses_normally(): void
    {
        $response = new Response(500);

        /** @var HttpClient&MockInterface $httpClient */
        $httpClient = $this->mock(HttpClient::class);
        $httpClient->shouldReceive('send')->with($this->request)->andReturn($response);

        $client = new Psr18Client($httpClient);
        $result = $client->sendRequest($this->request);

        self::assertSame($response, $result);
        self::assertSame(500, $result->getStatusCode());
    }

    public function test_that_send_request_maps_fight_network_exceptions_to_psr_network_exceptions(): void
    {
        /** @var HttpClient&MockInterface $httpClient */
        $httpClient = $this->mock(HttpClient::class);
        $httpClient->shouldReceive('send')->with($this->request)->andThrow(
            new FightNetworkException('Connection refused', $this->request)
        );

        $client = new Psr18Client($httpClient);

        try {
            $client->sendRequest($this->request);
            self::fail('Expected a PSR network exception.');
        } catch (NetworkExceptionInterface $exception) {
            self::assertSame($this->request, $exception->getRequest());
            self::assertSame('Connection refused', $exception->getMessage());
        }
    }

    public function test_that_send_request_maps_fight_request_exceptions_to_psr_request_exceptions(): void
    {
        /** @var HttpClient&MockInterface $httpClient */
        $httpClient = $this->mock(HttpClient::class);
        $httpClient->shouldReceive('send')->with($this->request)->andThrow(
            new FightRequestException('Request body is invalid', $this->request)
        );

        $client = new Psr18Client($httpClient);

        try {
            $client->sendRequest($this->request);
            self::fail('Expected a PSR request exception.');
        } catch (RequestExceptionInterface $exception) {
            self::assertSame($this->request, $exception->getRequest());
            self::assertSame('Request body is invalid', $exception->getMessage());
        }
    }

    public function test_that_send_request_maps_other_fight_transfer_exceptions_to_psr_client_exceptions(): void
    {
        /** @var HttpClient&MockInterface $httpClient */
        $httpClient = $this->mock(HttpClient::class);
        $httpClient->shouldReceive('send')->with($this->request)->andThrow(
            new TransferException('Transport failed')
        );

        $client = new Psr18Client($httpClient);

        $this->expectException(ClientExceptionInterface::class);
        $this->expectExceptionMessage('Transport failed');

        $client->sendRequest($this->request);
    }

    public function test_that_psr_client_does_not_implement_fight_http_client(): void
    {
        /** @var HttpClient&MockInterface $httpClient */
        $httpClient = $this->mock(HttpClient::class);
        $client = new Psr18Client($httpClient);

        self::assertInstanceOf(ClientInterface::class, $client);
        self::assertNotInstanceOf(HttpClient::class, $client);
        self::assertFalse(method_exists($client, 'sendAsync'));
    }
}
