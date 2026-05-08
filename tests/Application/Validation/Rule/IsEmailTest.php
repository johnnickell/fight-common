<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsEmail;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsEmail::class)]
class IsEmailTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_valid_email(): void
    {
        self::assertTrue((new IsEmail())->isSatisfiedBy('user@example.com'));
    }

    public function test_that_is_satisfied_by_returns_false_for_invalid_email(): void
    {
        self::assertFalse((new IsEmail())->isSatisfiedBy('notanemail'));
    }
}
