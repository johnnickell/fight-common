<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\KeyNotEmpty;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(KeyNotEmpty::class)]
class KeyNotEmptyTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_key_exists_and_is_not_empty(): void
    {
        self::assertTrue((new KeyNotEmpty('name'))->isSatisfiedBy(['name' => 'Alice']));
    }

    public function test_that_is_satisfied_by_returns_false_when_key_is_missing(): void
    {
        self::assertFalse((new KeyNotEmpty('name'))->isSatisfiedBy(['email' => 'alice@example.com']));
    }

    public function test_that_is_satisfied_by_returns_false_when_key_exists_but_is_empty(): void
    {
        self::assertFalse((new KeyNotEmpty('name'))->isSatisfiedBy(['name' => '']));
    }
}
