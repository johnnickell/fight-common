<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Internet;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StringObject;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(EmailAddress::class)]
class EmailAddressTest extends UnitTestCase
{
    // -------------------------------------------------------------------------
    // Creation
    // -------------------------------------------------------------------------

    public function test_that_from_string_creates_instance_for_valid_email(): void
    {
        $email = EmailAddress::fromString('user@example.com');

        self::assertSame('user@example.com', $email->toString());
    }

    public function test_that_from_string_throws_for_invalid_email(): void
    {
        $this->expectException(DomainException::class);
        EmailAddress::fromString('not-an-email');
    }

    public function test_that_from_string_throws_for_missing_at_symbol(): void
    {
        $this->expectException(DomainException::class);
        EmailAddress::fromString('userexample.com');
    }

    public function test_that_from_string_throws_for_missing_domain(): void
    {
        $this->expectException(DomainException::class);
        EmailAddress::fromString('user@');
    }

    public function test_that_from_string_throws_for_empty_string(): void
    {
        $this->expectException(DomainException::class);
        EmailAddress::fromString('');
    }

    // -------------------------------------------------------------------------
    // Parts
    // -------------------------------------------------------------------------

    public function test_that_local_part_returns_portion_before_at(): void
    {
        $email = EmailAddress::fromString('john.doe@example.com');

        self::assertSame('john.doe', $email->localPart());
    }

    public function test_that_local_part_handles_plus_addressing(): void
    {
        $email = EmailAddress::fromString('user+tag@example.com');

        self::assertSame('user+tag', $email->localPart());
    }

    public function test_that_domain_part_returns_portion_after_at(): void
    {
        $email = EmailAddress::fromString('user@example.com');

        self::assertSame('example.com', $email->domainPart());
    }

    public function test_that_quoted_email_parts_preserve_local_at_sign(): void
    {
        $email = EmailAddress::fromString('"a@b"@example.com');

        self::assertSame(['"a@b"', 'example.com'], [$email->localPart(), $email->domainPart()]);
    }

    #[DataProvider('acceptedParts')]
    public function test_that_parts_preserve_accepted_local_bytes_and_strip_only_domain_brackets(
        string $address,
        string $local,
        string $domain
    ): void {
        $email = EmailAddress::fromString($address);

        self::assertSame($local, $email->localPart());
        self::assertSame($domain, $email->domainPart());
        self::assertSame($address, $email->toString());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function acceptedParts(): iterable
    {
        yield 'ordinary' => ['john.doe@example.com', 'john.doe', 'example.com'];
        yield 'plus address' => ['user+tag@example.com', 'user+tag', 'example.com'];
        yield 'quoted without at' => ['"local"@example.com', '"local"', 'example.com'];
        yield 'multiple local at signs' => ['"a@b@c"@example.com', '"a@b@c"', 'example.com'];
        yield 'escaped quote' => ['"a\"@b"@example.com', '"a\"@b"', 'example.com'];
        yield 'escaped at sign' => ['"a\@b"@example.com', '"a\@b"', 'example.com'];
        yield 'escaped backslash' => ['"a\\\\b@c"@example.com', '"a\\\\b@c"', 'example.com'];
        yield 'quoted IPv4 literal' => ['"a@b"@[192.168.1.1]', '"a@b"', '192.168.1.1'];
        yield 'quoted IPv6 literal' => ['"a@b"@[IPv6:2001:db8::1]', '"a@b"', 'IPv6:2001:db8::1'];
        yield 'ordinary IPv6 literal' => ['user@[IPv6:2001:db8::1]', 'user', 'IPv6:2001:db8::1'];
    }

    #[DataProvider('invalidAddresses')]
    public function test_that_from_string_keeps_rejecting_invalid_address_syntax(string $address): void
    {
        $this->expectException(DomainException::class);

        EmailAddress::fromString($address);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAddresses(): iterable
    {
        yield 'unquoted local at sign' => ['a@b@example.com'];
        yield 'unclosed quote' => ['"a@b@example.com'];
        yield 'invalid domain literal' => ['"a@b"@[not-an-ip]'];
        yield 'domain at sign' => ['"a@b"@example@com'];
        yield 'leading whitespace' => [' "a@b"@example.com'];
        yield 'trailing whitespace' => ['"a@b"@example.com '];
        yield 'Unicode local part' => ['é@example.com'];
    }

    public function test_that_domain_part_strips_brackets(): void
    {
        $email = EmailAddress::fromString('user@[192.168.1.1]');

        self::assertSame('192.168.1.1', $email->domainPart());
    }

    // -------------------------------------------------------------------------
    // Canonical form
    // -------------------------------------------------------------------------

    public function test_that_canonical_returns_lowercase_value(): void
    {
        $email = EmailAddress::fromString('User@Example.COM');

        self::assertSame('user@example.com', $email->canonical());
    }

    public function test_that_canonical_preserves_already_lowercase(): void
    {
        $email = EmailAddress::fromString('user@example.com');

        self::assertSame('user@example.com', $email->canonical());
    }

    // -------------------------------------------------------------------------
    // Value interface
    // -------------------------------------------------------------------------

    public function test_that_to_string_returns_original_value(): void
    {
        $email = EmailAddress::fromString('User@Example.com');

        self::assertSame('User@Example.com', $email->toString());
    }

    public function test_that_cast_to_string_returns_original_value(): void
    {
        $email = EmailAddress::fromString('user@example.com');

        self::assertSame('user@example.com', (string) $email);
    }

    public function test_that_json_serialize_returns_string_value(): void
    {
        $email = EmailAddress::fromString('user@example.com');

        self::assertSame(json_encode('user@example.com'), json_encode($email));
    }

    public function test_that_equals_returns_true_for_same_email(): void
    {
        $email1 = EmailAddress::fromString('user@example.com');
        $email2 = EmailAddress::fromString('user@example.com');

        self::assertTrue($email1->equals($email2));
    }

    public function test_that_equals_returns_false_for_different_case_email(): void
    {
        $email1 = EmailAddress::fromString('user@example.com');
        $email2 = EmailAddress::fromString('USER@EXAMPLE.COM');

        self::assertFalse($email1->equals($email2));
    }

    public function test_that_equals_returns_false_for_different_email(): void
    {
        $email1 = EmailAddress::fromString('user@example.com');
        $email2 = EmailAddress::fromString('other@example.com');

        self::assertFalse($email1->equals($email2));
    }

    public function test_that_equals_returns_false_for_non_email_object(): void
    {
        $email = EmailAddress::fromString('user@example.com');

        self::assertFalse($email->equals('user@example.com'));
    }

    public function test_that_quoted_address_keeps_original_identity_and_separate_canonicalization(): void
    {
        $address = '"A\"@B"@Example.COM';
        $email = EmailAddress::fromString($address);
        $same = EmailAddress::fromString($address);
        $lowercase = EmailAddress::fromString('"a\"@b"@example.com');

        self::assertSame('"a\"@b"@example.com', $email->canonical());
        self::assertSame('"A\"@B"', $email->localPart());
        self::assertSame('Example.COM', $email->domainPart());
        self::assertSame($address, $email->toString());
        self::assertSame($address, (string) $email);
        self::assertSame($address, $email->jsonSerialize());
        self::assertSame(json_encode($address), json_encode($email));
        self::assertSame($address, $email->hashValue());
        self::assertTrue($email->equals($same));
        self::assertSame($email->hashValue(), $same->hashValue());
        self::assertFalse($email->equals($lowercase));
        self::assertNotSame($email->hashValue(), $lowercase->hashValue());
        self::assertFalse($email->equals(StringObject::fromString($address)));
        self::assertTrue($email->equals(EmailAddress::fromString(json_decode(json_encode($email), true))));
    }

    public function test_that_hash_value_returns_string_representation(): void
    {
        $email = EmailAddress::fromString('user@example.com');

        self::assertSame('user@example.com', $email->hashValue());
    }
}
