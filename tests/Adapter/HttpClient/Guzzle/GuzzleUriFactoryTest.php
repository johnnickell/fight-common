<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\HttpClient\Guzzle;

use Fight\Common\Adapter\HttpClient\Guzzle\GuzzleUriFactory;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\UriInterface;

#[CoversClass(GuzzleUriFactory::class)]
class GuzzleUriFactoryTest extends UnitTestCase
{
    private GuzzleUriFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new GuzzleUriFactory();
    }

    public function test_that_create_uri_returns_uri_for_valid_string(): void
    {
        $uri = $this->factory->createUri('https://example.com/path?q=1');

        self::assertInstanceOf(UriInterface::class, $uri);
        self::assertSame('https', $uri->getScheme());
    }

    public function test_that_create_uri_throws_domain_exception_for_invalid_uri(): void
    {
        $this->expectException(DomainException::class);
        $this->factory->createUri('///invalid uri with spaces');
    }
}
