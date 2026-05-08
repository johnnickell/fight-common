<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsUrn;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsUrn::class)]
class IsUrnTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_valid_urn(): void
    {
        self::assertTrue((new IsUrn())->isSatisfiedBy('urn:isbn:0451450523'));
    }

    public function test_that_is_satisfied_by_returns_false_for_plain_string(): void
    {
        self::assertFalse((new IsUrn())->isSatisfiedBy('plainstring'));
    }
}
