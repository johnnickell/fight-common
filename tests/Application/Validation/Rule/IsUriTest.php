<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Rule;

use Fight\Common\Application\Validation\Rule\IsUri;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IsUri::class)]
class IsUriTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_for_valid_uri(): void
    {
        self::assertTrue(new IsUri()->isSatisfiedBy('https://example.com/path'));
    }

    public function test_that_is_satisfied_by_returns_false_for_invalid_uri(): void
    {
        self::assertFalse(new IsUri()->isSatisfiedBy('not a uri'));
    }
}
