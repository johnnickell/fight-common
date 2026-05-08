<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\InList;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InList::class)]
class InListTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_value_is_in_list(): void
    {
        self::assertTrue((new InList(['red', 'green', 'blue']))->isSatisfiedBy('green'));
    }

    public function test_that_is_satisfied_by_returns_false_when_value_is_not_in_list(): void
    {
        self::assertFalse((new InList(['red', 'green', 'blue']))->isSatisfiedBy('yellow'));
    }
}
