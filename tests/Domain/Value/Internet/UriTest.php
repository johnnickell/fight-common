<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Internet;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Internet\Uri;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Uri::class)]
class UriTest extends UnitTestCase
{
    private const FULL_URI = 'http://user:pass@example.com:8080/path/to/resource?foo=bar&baz=qux#section1';

    // -------------------------------------------------------------------------
    // fromString
    // -------------------------------------------------------------------------

    public function test_that_from_string_parses_a_full_uri_with_all_components(): void
    {
        $uri = Uri::fromString(self::FULL_URI);

        self::assertSame('http', $uri->scheme());
        self::assertSame('user:pass@example.com:8080', $uri->authority());
        self::assertSame('/path/to/resource', $uri->path());
        self::assertSame('foo=bar&baz=qux', $uri->query());
        self::assertSame('section1', $uri->fragment());
    }

    public function test_that_from_string_throws_for_a_path_only_uri(): void
    {
        $this->expectException(DomainException::class);
        Uri::fromString('/some/path');
    }

    public function test_that_from_string_throws_for_an_empty_string(): void
    {
        $this->expectException(DomainException::class);
        Uri::fromString('');
    }

    // -------------------------------------------------------------------------
    // fromArray
    // -------------------------------------------------------------------------

    public function test_that_from_array_creates_instance_from_valid_components(): void
    {
        $uri = Uri::fromArray([
            'scheme'    => 'https',
            'authority' => 'example.com',
            'path'      => '/test',
            'query'     => 'key=value',
            'fragment'  => 'anchor',
        ]);

        self::assertSame('https', $uri->scheme());
        self::assertSame('example.com', $uri->authority());
        self::assertSame('/test', $uri->path());
        self::assertSame('key=value', $uri->query());
        self::assertSame('anchor', $uri->fragment());
    }

    // -------------------------------------------------------------------------
    // Wither methods
    // -------------------------------------------------------------------------

    public function test_that_with_scheme_returns_new_instance_with_updated_scheme_and_leaves_original_unchanged(): void
    {
        $original = Uri::fromString(self::FULL_URI);
        $updated = $original->withScheme('https');

        self::assertSame('https', $updated->scheme());
        self::assertSame('http', $original->scheme());
    }

    public function test_that_with_authority_returns_new_instance_with_updated_authority(): void
    {
        $original = Uri::fromString(self::FULL_URI);
        $updated = $original->withAuthority('newhost.com');

        self::assertSame('newhost.com', $updated->authority());
        self::assertSame('user:pass@example.com:8080', $original->authority());
    }

    public function test_that_with_path_returns_new_instance_with_updated_path(): void
    {
        $original = Uri::fromString(self::FULL_URI);
        $updated = $original->withPath('/new/path');

        self::assertSame('/new/path', $updated->path());
        self::assertSame('/path/to/resource', $original->path());
    }

    public function test_that_with_query_returns_new_instance_with_updated_query(): void
    {
        $original = Uri::fromString(self::FULL_URI);
        $updated = $original->withQuery('new=query');

        self::assertSame('new=query', $updated->query());
        self::assertSame('foo=bar&baz=qux', $original->query());
    }

    public function test_that_with_fragment_returns_new_instance_with_updated_fragment(): void
    {
        $original = Uri::fromString(self::FULL_URI);
        $updated = $original->withFragment('newfrag');

        self::assertSame('newfrag', $updated->fragment());
        self::assertSame('section1', $original->fragment());
    }

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    public function test_that_with_scheme_throws_for_an_invalid_scheme(): void
    {
        $uri = Uri::fromString(self::FULL_URI);

        $this->expectException(DomainException::class);
        $uri->withScheme('123invalid');
    }

    // -------------------------------------------------------------------------
    // Output
    // -------------------------------------------------------------------------

    public function test_that_to_string_reassembles_the_uri_correctly(): void
    {
        $uri = Uri::fromString(self::FULL_URI);

        self::assertSame(self::FULL_URI, $uri->toString());
    }

    public function test_that_cast_to_string_returns_the_same_value_as_to_string(): void
    {
        $uri = Uri::fromString(self::FULL_URI);

        self::assertSame($uri->toString(), (string) $uri);
    }

    public function test_that_display_returns_uri_without_user_info(): void
    {
        $uri = Uri::fromString(self::FULL_URI);

        self::assertSame('http://example.com:8080/path/to/resource?foo=bar&baz=qux#section1', $uri->display());
    }
}
