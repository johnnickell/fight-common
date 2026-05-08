<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Internet;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Internet\Url;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Url::class)]
class UrlTest extends UnitTestCase
{
    // -------------------------------------------------------------------------
    // fromString
    // -------------------------------------------------------------------------

    public function test_that_from_string_creates_instance_from_a_valid_url(): void
    {
        // query is sorted by key on construction: b=2&a=1 → a=1&b=2
        $url = Url::fromString('https://example.com/path?b=2&a=1#section');

        self::assertSame('https', $url->scheme());
        self::assertSame('example.com', $url->authority());
        self::assertSame('/path', $url->path());
        self::assertSame('a=1&b=2', $url->query());
        self::assertSame('section', $url->fragment());
    }

    public function test_that_from_string_throws_for_a_url_with_no_scheme(): void
    {
        $this->expectException(DomainException::class);
        Url::fromString('//example.com/path');
    }

    // -------------------------------------------------------------------------
    // normalizeQuery — null and empty branches
    // -------------------------------------------------------------------------

    public function test_that_query_is_null_when_no_query_component_is_present(): void
    {
        $url = Url::fromString('https://example.com/');

        self::assertNull($url->query());
    }

    public function test_that_query_is_empty_string_when_query_marker_is_present_but_empty(): void
    {
        $url = Url::fromString('https://example.com/?');

        self::assertSame('', $url->query());
    }

    // -------------------------------------------------------------------------
    // normalizeQuery — sorting
    // -------------------------------------------------------------------------

    public function test_that_normalize_query_returns_params_in_sorted_key_order(): void
    {
        $url = Url::fromString('https://example.com/?z=last&a=first&m=middle');

        self::assertSame('a=first&m=middle&z=last', $url->query());
    }

    // -------------------------------------------------------------------------
    // normalizeQuery — removal of invalid params
    // -------------------------------------------------------------------------

    public function test_that_normalize_query_removes_params_whose_key_is_empty(): void
    {
        // '=nokey' starts with '=' so its key is empty and the param is dropped
        $url = Url::fromString('https://example.com/?a=1&=nokey&b=2');

        self::assertSame('a=1&b=2', $url->query());
    }

    public function test_that_normalize_query_removes_empty_params_produced_by_consecutive_ampersands(): void
    {
        // '&&' produces an empty string segment which is dropped
        $url = Url::fromString('https://example.com/?a=1&&b=2');

        self::assertSame('a=1&b=2', $url->query());
    }

    // -------------------------------------------------------------------------
    // DEFAULT_PORTS — port elision
    // -------------------------------------------------------------------------

    public function test_that_default_port_80_is_elided_from_http_urls(): void
    {
        $url = Url::fromString('http://example.com:80/');

        self::assertNull($url->port());
        self::assertSame('example.com', $url->authority());
    }

    public function test_that_default_port_443_is_elided_from_https_urls(): void
    {
        $url = Url::fromString('https://example.com:443/');

        self::assertNull($url->port());
        self::assertSame('example.com', $url->authority());
    }

    // -------------------------------------------------------------------------
    // withScheme
    // -------------------------------------------------------------------------

    public function test_that_with_scheme_throws_for_a_format_invalid_scheme(): void
    {
        $url = Url::fromString('https://example.com/');

        $this->expectException(DomainException::class);
        $url->withScheme('123bad');
    }

    public function test_that_with_scheme_throws_for_a_non_http_https_scheme(): void
    {
        // Regression: Url previously accepted any RFC 3986-valid scheme (e.g. ftp)
        // because isValidScheme was not overridden to restrict to http/https.
        $url = Url::fromString('https://example.com/');

        $this->expectException(DomainException::class);
        $url->withScheme('ftp');
    }

    // -------------------------------------------------------------------------
    // toString
    // -------------------------------------------------------------------------

    public function test_that_to_string_returns_the_assembled_url_with_sorted_query(): void
    {
        $url = Url::fromString('https://example.com/path?b=2&a=1#section');

        self::assertSame('https://example.com/path?a=1&b=2#section', $url->toString());
    }

    // -------------------------------------------------------------------------
    // Equality
    // -------------------------------------------------------------------------

    public function test_that_equals_returns_true_for_two_urls_from_the_same_string(): void
    {
        $url1 = Url::fromString('https://example.com/');
        $url2 = Url::fromString('https://example.com/');

        self::assertTrue($url1->equals($url2));
    }

    public function test_that_equals_returns_false_for_two_different_urls(): void
    {
        $url1 = Url::fromString('https://example.com/');
        $url2 = Url::fromString('https://other.com/');

        self::assertFalse($url1->equals($url2));
    }

    public function test_that_equals_returns_false_for_a_non_url_object(): void
    {
        $url = Url::fromString('https://example.com/');

        self::assertFalse($url->equals(new \stdClass()));
    }

    // -------------------------------------------------------------------------
    // hashValue
    // -------------------------------------------------------------------------

    public function test_that_hash_value_returns_the_same_string_for_two_urls_from_the_same_string(): void
    {
        $url1 = Url::fromString('https://example.com/');
        $url2 = Url::fromString('https://example.com/');

        self::assertSame($url1->hashValue(), $url2->hashValue());
    }

    // -------------------------------------------------------------------------
    // compareTo
    // -------------------------------------------------------------------------

    public function test_that_compare_to_returns_zero_for_equal_urls(): void
    {
        $url1 = Url::fromString('https://example.com/');
        $url2 = Url::fromString('https://example.com/');

        self::assertSame(0, $url1->compareTo($url2));
    }

    public function test_that_compare_to_returns_negative_for_a_lesser_url(): void
    {
        $lesser  = Url::fromString('https://a.example.com/');
        $greater = Url::fromString('https://b.example.com/');

        self::assertLessThan(0, $lesser->compareTo($greater));
    }

    public function test_that_compare_to_returns_positive_for_a_greater_url(): void
    {
        $lesser  = Url::fromString('https://a.example.com/');
        $greater = Url::fromString('https://b.example.com/');

        self::assertGreaterThan(0, $greater->compareTo($lesser));
    }
}
