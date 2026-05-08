<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Internet;

use stdClass;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Internet\Uri;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Uri::class)]
class UriTest extends UnitTestCase
{
    private const string FULL_URI = 'http://user:pass@example.com:8080/path/to/resource?foo=bar&baz=qux#section1';

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

    // -------------------------------------------------------------------------
    // parse
    // -------------------------------------------------------------------------

    public function test_that_parse_returns_expected_instance_scheme()
    {
        $uri = Uri::parse('HTTPS://www.google.com');

        self::assertSame('https', $uri->scheme());
    }

    public function test_that_parse_returns_expected_instance_authority()
    {
        $uri = Uri::parse('https://username:password@mydomain.net:8110');

        self::assertSame('username:password@mydomain.net:8110', $uri->authority());
    }

    public function test_that_parse_returns_expected_null_authority()
    {
        $uri = Uri::parse('mailto:smith@example.com');

        self::assertNull($uri->authority());
    }

    public function test_that_parse_returns_expected_empty_authority()
    {
        $uri = Uri::parse('file:///etc/hosts');

        self::assertSame('', $uri->authority());
    }

    public function test_that_parse_returns_expected_instance_user_info()
    {
        $uri = Uri::parse('https://username:password@mydomain.net:8110');

        self::assertSame('username:password', $uri->userInfo());
    }

    public function test_that_parse_returns_expected_null_user_info()
    {
        $uri = Uri::parse('https://mydomain.net:8110');

        self::assertNull($uri->userInfo());
    }

    public function test_that_parse_returns_expected_instance_ipvfuture()
    {
        $uri = Uri::parse('https://[v1a.1080::8:800:200c:417a]/path');

        self::assertSame('[v1a.1080::8:800:200c:417a]', $uri->host());
    }

    public function test_that_parse_returns_expected_instance_ipv6()
    {
        $uri = Uri::parse('https://[1080:0:0:0:8:800:200c:417a]/path');

        self::assertSame('[1080:0:0:0:8:800:200c:417a]', $uri->host());
    }

    public function test_that_parse_returns_expected_instance_ipv4()
    {
        $uri = Uri::parse('https://127.0.0.1/path');

        self::assertSame('127.0.0.1', $uri->host());
    }

    public function test_that_parse_returns_expected_instance_host()
    {
        $uri = Uri::parse('https://username:password@mydomain.net:8110');

        self::assertSame('mydomain.net', $uri->host());
    }

    public function test_that_parse_returns_expected_instance_port()
    {
        $uri = Uri::parse('https://username:password@mydomain.net:8110');

        self::assertSame(8110, $uri->port());
    }

    public function test_that_parse_returns_expected_null_port()
    {
        $uri = Uri::parse('https://username:password@mydomain.net');

        self::assertNull($uri->port());
    }

    public function test_that_parse_returns_expected_missing_port()
    {
        // URI producers and normalizers should omit the ":" delimiter that
        // separates host from port if the port component is empty
        $uri = Uri::parse('https://username:password@mydomain.net:');

        self::assertSame('mydomain.net', $uri->host());
    }

    public function test_that_parse_returns_expected_instance_path()
    {
        $uri = Uri::parse('https://application.net/path/to/file.txt');

        self::assertSame('/path/to/file.txt', $uri->path());
    }

    public function test_that_parse_returns_expected_empty_path()
    {
        $uri = Uri::parse('https://application.net');

        self::assertSame('', $uri->path());
    }

    public function test_that_parse_returns_expected_instance_query()
    {
        $uri = Uri::parse('https://application.net/path?foo=bar&action=seek');

        self::assertSame('foo=bar&action=seek', $uri->query());
    }

    public function test_that_parse_returns_expected_null_query()
    {
        $uri = Uri::parse('https://application.net/path');

        self::assertNull($uri->query());
    }

    public function test_that_parse_returns_expected_empty_query()
    {
        $uri = Uri::parse('https://application.net/path?');

        self::assertSame('', $uri->query());
    }

    public function test_that_parse_returns_expected_encoded_query()
    {
        $uri = Uri::parse('https://application.net/path?q=foo%2Ebar');

        self::assertSame('q=foo.bar', $uri->query());
    }

    public function test_that_parse_returns_expected_instance_fragment()
    {
        $uri = Uri::parse('https://application.net/path#section1.03');

        self::assertSame('section1.03', $uri->fragment());
    }

    public function test_that_parse_returns_expected_null_fragment()
    {
        $uri = Uri::parse('https://application.net/path');

        self::assertNull($uri->fragment());
    }

    public function test_that_parse_returns_expected_empty_fragment()
    {
        $uri = Uri::parse('https://application.net/path#');

        self::assertSame('', $uri->fragment());
    }

    public function test_that_parse_returns_expected_encoded_fragment()
    {
        $uri = Uri::parse('https://application.net/path#%3Cfragment%3E');

        self::assertSame('%3Cfragment%3E', $uri->fragment());
    }

    // -------------------------------------------------------------------------
    // resolve
    // -------------------------------------------------------------------------

    public function test_that_resolve_with_an_absolute_reference_returns_the_reference_unchanged(): void
    {
        $resolved = Uri::resolve('http://base.com/foo/bar', 'https://other.com/new/path');

        self::assertSame('https://other.com/new/path', $resolved->toString());
    }

    public function test_that_resolve_with_a_relative_path_merges_it_against_the_base(): void
    {
        $resolved = Uri::resolve('http://example.com/a/b/c', 'new');

        self::assertSame('http://example.com/a/b/new', $resolved->toString());
    }

    public function test_that_resolve_with_a_query_only_reference_inherits_base_scheme_authority_and_path(): void
    {
        $resolved = Uri::resolve('http://example.com/path?old=1', '?foo=bar');

        self::assertSame('http://example.com/path?foo=bar', $resolved->toString());
    }

    public function test_that_resolve_with_a_fragment_only_reference_inherits_base_scheme_authority_path_and_query(): void
    {
        $resolved = Uri::resolve('http://example.com/path?query=1', '#section');

        self::assertSame('http://example.com/path?query=1#section', $resolved->toString());
    }

    public function test_that_resolve_with_an_empty_reference_returns_the_base_uri(): void
    {
        $resolved = Uri::resolve('http://example.com/path?q=1', '');

        self::assertSame('http://example.com/path?q=1', $resolved->toString());
    }

    public function test_that_resolve_with_dotdot_segment_resolves_the_parent_path(): void
    {
        $resolved = Uri::resolve('http://example.com/a/b/c', '../d');

        self::assertSame('http://example.com/a/d', $resolved->toString());
    }

    /**
     * @see https://tools.ietf.org/html/rfc3986#section-5.4
     */
    public static function provideReferenceResolutionExamples(): array
    {
        return [
            ["g:h", "g:h"],
            ["g", "http://a/b/c/g"],
            ["./g", "http://a/b/c/g"],
            ["g/", "http://a/b/c/g/"],
            ["/g", "http://a/g"],
            ["//g", "http://g"],
            ["?y", "http://a/b/c/d;p?y"],
            ["g?y", "http://a/b/c/g?y"],
            ["#s", "http://a/b/c/d;p?q#s"],
            ["g#s", "http://a/b/c/g#s"],
            ["g?y#s", "http://a/b/c/g?y#s"],
            [";x", "http://a/b/c/;x"],
            ["g;x", "http://a/b/c/g;x"],
            ["g;x?y#s", "http://a/b/c/g;x?y#s"],
            ["", "http://a/b/c/d;p?q"],
            [".", "http://a/b/c/"],
            ["./", "http://a/b/c/"],
            ["..", "http://a/b/"],
            ["../", "http://a/b/"],
            ["../g", "http://a/b/g"],
            ["../..", "http://a/"],
            ["../../", "http://a/"],
            ["../../g", "http://a/g"],
            ["../../../g", "http://a/g"],
            ["../../../../g", "http://a/g"],
            ["/./g", "http://a/g"],
            ["/../g", "http://a/g"],
            ["g.", "http://a/b/c/g."],
            [".g", "http://a/b/c/.g"],
            ["g..", "http://a/b/c/g.."],
            ["..g", "http://a/b/c/..g"],
            ["./../g", "http://a/b/g"],
            ["./g/.", "http://a/b/c/g/"],
            ["g/./h", "http://a/b/c/g/h"],
            ["g/../h", "http://a/b/c/h"],
            ["g;x=1/./y", "http://a/b/c/g;x=1/y"],
            ["g;x=1/../y", "http://a/b/c/y"],
            ["g?y/./x", "http://a/b/c/g?y/./x"],
            ["g?y/../x", "http://a/b/c/g?y/../x"],
            ["g#s/./x", "http://a/b/c/g#s/./x"],
            ["g#s/../x", "http://a/b/c/g#s/../x"],
            ["http:g", "http:g"]
        ];
    }

    #[DataProvider('provideReferenceResolutionExamples')]
    public function test_that_resolve_passes_rfc3986_examples($ref, $expected)
    {
        // http://tools.ietf.org/html/rfc3986#section-5.4
        $base = 'http://a/b/c/d;p?q';
        $uri = Uri::resolve($base, $ref);

        self::assertSame($expected, $uri->toString());
    }

    public function test_that_resolve_passes_rfc3986_non_strict_with_flag()
    {
        // http://tools.ietf.org/html/rfc3986#section-5.4
        $base = 'http://a/b/c/d;p?q';
        $uri = Uri::resolve($base, 'http:g', false);

        self::assertSame('http://a/b/c/g', $uri->toString());
    }

    public function test_that_resolve_returns_expected_instance_empty_base_path()
    {
        $base = 'http://app.dev';
        $uri = Uri::resolve($base, '.');

        self::assertSame('http://app.dev/', $uri->toString());
    }

    public function test_that_resolve_returns_expected_instance_with_null_base_authority()
    {
        $base = 'mailto:smith@example.com';
        $uri = Uri::resolve($base, '.');

        self::assertSame('mailto:', $uri->toString());
    }

    public function test_that_resolve_returns_expected_instance_with_null_base_authority_2()
    {
        $base = 'mailto:smith@example.com';
        $uri = Uri::resolve($base, './');

        self::assertSame('mailto:', $uri->toString());
    }

    public function test_that_resolve_returns_expected_instance_with_null_base_authority_3()
    {
        $base = 'mailto:smith@example.com';
        $uri = Uri::resolve($base, '../');

        self::assertSame('mailto:', $uri->toString());
    }

    // -------------------------------------------------------------------------
    // Equality
    // -------------------------------------------------------------------------

    public function test_that_equals_returns_true_for_two_uris_created_from_the_same_string(): void
    {
        $uri1 = Uri::fromString(self::FULL_URI);
        $uri2 = Uri::fromString(self::FULL_URI);

        self::assertTrue($uri1->equals($uri2));
    }

    public function test_that_equals_returns_false_for_two_different_uris(): void
    {
        $uri1 = Uri::fromString(self::FULL_URI);
        $uri2 = Uri::fromString('http://other.com/');

        self::assertFalse($uri1->equals($uri2));
    }

    public function test_that_equals_returns_false_for_a_non_uri_object(): void
    {
        $uri = Uri::fromString(self::FULL_URI);

        self::assertFalse($uri->equals(new stdClass()));
    }

    public function test_that_hash_value_returns_the_same_string_for_two_uris_from_the_same_string(): void
    {
        $uri1 = Uri::fromString(self::FULL_URI);
        $uri2 = Uri::fromString(self::FULL_URI);

        self::assertSame($uri1->hashValue(), $uri2->hashValue());
    }

    // -------------------------------------------------------------------------
    // Comparison
    // -------------------------------------------------------------------------

    public function test_that_compare_to_returns_zero_for_equal_uris(): void
    {
        $uri1 = Uri::fromString(self::FULL_URI);
        $uri2 = Uri::fromString(self::FULL_URI);

        self::assertSame(0, $uri1->compareTo($uri2));
    }

    public function test_that_compare_to_returns_negative_for_a_lesser_uri(): void
    {
        $lesser  = Uri::fromString('http://a.example.com/');
        $greater = Uri::fromString('http://b.example.com/');

        self::assertLessThan(0, $lesser->compareTo($greater));
    }

    public function test_that_compare_to_returns_positive_for_a_greater_uri(): void
    {
        $lesser  = Uri::fromString('http://a.example.com/');
        $greater = Uri::fromString('http://b.example.com/');

        self::assertGreaterThan(0, $greater->compareTo($lesser));
    }

    public function test_that_from_array_returns_expected_instance()
    {
        $uri = Uri::fromArray([
            'scheme'    => 'http',
            'authority' => 'myapp.com',
            'path'      => '/action',
            'query'     => 'foo=bar',
            'fragment'  => '!wha'
        ]);

        self::assertSame('http://myapp.com/action?foo=bar#!wha', $uri->toString());
    }

    public function test_that_to_array_returns_expected_value()
    {
        $uri = Uri::parse('http://myapp.com/action?foo=bar#!wha');
        $expected = [
            'scheme'    => 'http',
            'authority' => 'myapp.com',
            'path'      => '/action',
            'query'     => 'foo=bar',
            'fragment'  => '!wha'
        ];

        self::assertSame($expected, $uri->toArray());
    }

    public function test_that_to_string_returns_user_info()
    {
        $uri = Uri::parse('https://user:secret@myapp.com:8080/action?foo=bar#!wha');

        self::assertSame('https://user:secret@myapp.com:8080/action?foo=bar#!wha', $uri->toString());
    }

    public function test_that_display_does_not_return_user_info()
    {
        $uri = Uri::parse('https://user:secret@myapp.com:8080/action?foo=bar#!wha');

        self::assertSame('https://myapp.com:8080/action?foo=bar#!wha', $uri->display());
    }

    public function test_that_compare_to_returns_zero_for_same_instance()
    {
        $uri = Uri::parse('https://user:secret@myapp.com:8080/action?foo=bar#!wha');

        self::assertSame(0, $uri->compareTo($uri));
    }
}
