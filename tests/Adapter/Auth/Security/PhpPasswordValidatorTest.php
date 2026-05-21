<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Auth\Security;

use Fight\Common\Adapter\Auth\Security\PhpPasswordValidator;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PhpPasswordValidator::class)]
class PhpPasswordValidatorTest extends UnitTestCase
{
    public function test_that_validate_returns_true_when_password_matches_hash(): void
    {
        $hash = password_hash('secret', PASSWORD_BCRYPT);
        $validator = new PhpPasswordValidator(PASSWORD_BCRYPT);

        self::assertTrue($validator->validate('secret', $hash));
    }

    public function test_that_validate_returns_false_when_password_does_not_match(): void
    {
        $hash = password_hash('secret', PASSWORD_BCRYPT);
        $validator = new PhpPasswordValidator(PASSWORD_BCRYPT);

        self::assertFalse($validator->validate('wrong', $hash));
    }

    public function test_that_needs_rehash_returns_false_for_freshly_hashed_password(): void
    {
        $hash = password_hash('secret', PASSWORD_BCRYPT);
        $validator = new PhpPasswordValidator(PASSWORD_BCRYPT);

        self::assertFalse($validator->needsRehash($hash));
    }

    public function test_that_needs_rehash_returns_true_when_hash_needs_rehashing(): void
    {
        $hash = password_hash('secret', PASSWORD_BCRYPT, ['cost' => 4]);
        $validator = new PhpPasswordValidator(PASSWORD_BCRYPT, ['cost' => 12]);

        self::assertTrue($validator->needsRehash($hash));
    }

    public function test_that_needs_rehash_returns_false_for_hash_matching_current_options(): void
    {
        $hash = password_hash('secret', PASSWORD_BCRYPT, ['cost' => 4]);
        $validator = new PhpPasswordValidator(PASSWORD_BCRYPT, ['cost' => 4]);

        self::assertFalse($validator->needsRehash($hash));
    }
}
