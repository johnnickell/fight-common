<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsAlnumDashed;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsAlnumDashed::class)]
class IsAlnumDashedTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_hyphenated_alphanumeric_string(): void
    {
        self::assertTrue((new IsAlnumDashed())->isSatisfiedBy('hello-1'));
    }

    public function test_that_is_satisfied_by_returns_false_for_string_with_spaces(): void
    {
        self::assertFalse((new IsAlnumDashed())->isSatisfiedBy('hello 1'));
    }
}
