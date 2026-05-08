<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsJson;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsJson::class)]
class IsJsonTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_valid_json(): void
    {
        self::assertTrue(new IsJson()->isSatisfiedBy('{"key":"value"}'));
    }

    public function test_that_is_satisfied_by_returns_false_for_invalid_json(): void
    {
        self::assertFalse(new IsJson()->isSatisfiedBy('notjson'));
    }
}
