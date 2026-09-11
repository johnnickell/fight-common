<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Auth\Hmac;

use Fight\Common\Adapter\Auth\Hmac\HmacWebhookDispatcher;
use Fight\Common\Application\Auth\RequestService;
use Fight\Common\Application\HttpClient\Message\MessageFactory;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(HmacWebhookDispatcher::class)]
class HmacWebhookDispatcherTest extends UnitTestCase
{
    public function test_that_dispatch_signs_and_sends_request(): void
    {
        $url = 'https://example.com/ops';

        /** @var MockInterface|RequestInterface $unsignedRequest */
        $unsignedRequest = $this->mock(RequestInterface::class);

        /** @var MockInterface|RequestInterface $signedRequest */
        $signedRequest = $this->mock(RequestInterface::class);

        /** @var MockInterface|ResponseInterface $response */
        $response = $this->mock(ResponseInterface::class);

        /** @var MockInterface|MessageFactory $factory */
        $factory = $this->mock(MessageFactory::class);
        $factory->shouldReceive('createRequest')
            ->once()
            ->withArgs(fn(string $method, string $u, array $headers, string $body): bool =>
                $method === 'POST'
                && $u === $url
                && $headers['Content-Type'] === 'application/json'
                && str_contains($body, '"health_check"')
            )
            ->andReturn($unsignedRequest);

        /** @var MockInterface|RequestService $signer */
        $signer = $this->mock(RequestService::class);
        $signer->shouldReceive('signRequest')->with($unsignedRequest)->once()->andReturn($signedRequest);

        /** @var MockInterface|HttpClient $client */
        $client = $this->mock(HttpClient::class);
        $client->shouldReceive('send')->with($signedRequest)->once()->andReturn($response);

        $dispatcher = new HmacWebhookDispatcher($client, $factory, $signer);
        $dispatcher->dispatch($url, 'health_check');
    }

    public function test_that_dispatch_includes_payload_in_body(): void
    {
        /** @var MockInterface|RequestInterface $request */
        $request = $this->mock(RequestInterface::class);

        /** @var MockInterface|ResponseInterface $response */
        $response = $this->mock(ResponseInterface::class);

        /** @var MockInterface|MessageFactory $factory */
        $factory = $this->mock(MessageFactory::class);
        $factory->shouldReceive('createRequest')
            ->once()
            ->withArgs(fn(string $m, string $u, array $h, string $body): bool =>
                str_contains($body, '"version"') && str_contains($body, '"1.2.3"')
            )
            ->andReturn($request);

        /** @var MockInterface|RequestService $signer */
        $signer = $this->mock(RequestService::class);
        $signer->shouldReceive('signRequest')->andReturn($request);

        /** @var MockInterface|HttpClient $client */
        $client = $this->mock(HttpClient::class);
        $client->shouldReceive('send')->andReturn($response);

        $dispatcher = new HmacWebhookDispatcher($client, $factory, $signer);
        $dispatcher->dispatch('https://example.com/ops', 'deploy', ['version' => '1.2.3']);
    }

    public function test_that_dispatch_rejects_an_unknown_action_before_creating_a_request(): void
    {
        /** @var MockInterface|MessageFactory $factory */
        $factory = $this->mock(MessageFactory::class);
        $factory->shouldNotReceive('createRequest');

        /** @var MockInterface|RequestService $signer */
        $signer = $this->mock(RequestService::class);
        $signer->shouldNotReceive('signRequest');

        /** @var MockInterface|HttpClient $client */
        $client = $this->mock(HttpClient::class);
        $client->shouldNotReceive('send');

        $dispatcher = new HmacWebhookDispatcher($client, $factory, $signer);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown AI operation action: unknown');

        $dispatcher->dispatch('https://example.com/ops', 'unknown');
    }
}
