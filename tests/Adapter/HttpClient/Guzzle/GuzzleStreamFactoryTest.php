<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\HttpClient\Guzzle;

use Fight\Common\Adapter\HttpClient\Guzzle\GuzzleStreamFactory;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\StreamInterface;

#[CoversClass(GuzzleStreamFactory::class)]
class GuzzleStreamFactoryTest extends UnitTestCase
{
    private GuzzleStreamFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new GuzzleStreamFactory();
    }

    public function test_that_create_stream_returns_stream_for_string_body(): void
    {
        $stream = $this->factory->createStream('hello world');

        self::assertInstanceOf(StreamInterface::class, $stream);
        self::assertSame('hello world', (string) $stream);
    }

    public function test_that_create_stream_returns_stream_for_null_body(): void
    {
        $stream = $this->factory->createStream(null);

        self::assertInstanceOf(StreamInterface::class, $stream);
    }

    public function test_that_create_stream_throws_domain_exception_for_invalid_body(): void
    {
        $this->expectException(DomainException::class);
        $this->factory->createStream(new \stdClass());
    }
}
