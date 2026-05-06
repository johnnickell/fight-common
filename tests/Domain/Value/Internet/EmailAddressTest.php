<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Internet;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

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

    public function test_that_hash_value_returns_string_representation(): void
    {
        $email = EmailAddress::fromString('user@example.com');

        self::assertSame('user@example.com', $email->hashValue());
    }
}
