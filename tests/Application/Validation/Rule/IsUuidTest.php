<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsUuid;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsUuid::class)]
class IsUuidTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_valid_uuid(): void
    {
        self::assertTrue((new IsUuid())->isSatisfiedBy('550e8400-e29b-41d4-a716-446655440000'));
    }

    public function test_that_is_satisfied_by_returns_false_for_non_uuid_string(): void
    {
        self::assertFalse((new IsUuid())->isSatisfiedBy('notauuid'));
    }
}
