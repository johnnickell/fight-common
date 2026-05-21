<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\HttpClient\Guzzle;

use Fight\Common\Adapter\HttpClient\Guzzle\GuzzleMessageFactory;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(GuzzleMessageFactory::class)]
class GuzzleMessageFactoryTest extends UnitTestCase
{
    private GuzzleMessageFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new GuzzleMessageFactory();
    }

    public function test_that_create_request_returns_request_with_given_method_and_uri(): void
    {
        $request = $this->factory->createRequest('GET', 'https://example.com/api');

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://example.com/api', (string) $request->getUri());
    }

    public function test_that_create_response_returns_response_with_given_status(): void
    {
        $response = $this->factory->createResponse(201);

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame(201, $response->getStatusCode());
    }
}
