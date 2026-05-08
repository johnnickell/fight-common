<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\KeyIsset;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(KeyIsset::class)]
class KeyIssetTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_key_exists(): void
    {
        self::assertTrue((new KeyIsset('name'))->isSatisfiedBy(['name' => 'Alice']));
    }

    public function test_that_is_satisfied_by_returns_false_when_key_is_missing(): void
    {
        self::assertFalse((new KeyIsset('name'))->isSatisfiedBy(['email' => 'alice@example.com']));
    }
}
