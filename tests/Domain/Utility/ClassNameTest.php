<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Utility;

use Fight\Common\Domain\Exception\RuntimeException;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ClassName::class)]
class ClassNameTest extends UnitTestCase
{
    public function test_that_full_returns_backslash_class_name_from_object(): void
    {
        $exception = new RuntimeException('test');

        self::assertSame(RuntimeException::class, ClassName::full($exception));
    }

    public function test_that_full_returns_backslash_class_name_from_string(): void
    {
        self::assertSame(RuntimeException::class, ClassName::full(RuntimeException::class));
    }

    public function test_that_full_converts_dot_notation_to_backslash(): void
    {
        $dotted = 'Fight.Common.Domain.Exception.RuntimeException';

        self::assertSame(RuntimeException::class, ClassName::full($dotted));
    }

    public function test_that_canonical_returns_dot_separated_name_from_object(): void
    {
        $exception = new RuntimeException('test');
        $expected = 'Fight.Common.Domain.Exception.RuntimeException';

        self::assertSame($expected, ClassName::canonical($exception));
    }

    public function test_that_canonical_returns_dot_separated_name_from_string(): void
    {
        $expected = 'Fight.Common.Domain.Exception.RuntimeException';

        self::assertSame($expected, ClassName::canonical(RuntimeException::class));
    }

    public function test_that_underscore_returns_lowercase_underscored_name_from_object(): void
    {
        $exception = new RuntimeException('test');
        $expected = 'fight.common.domain.exception.runtime_exception';

        self::assertSame($expected, ClassName::underscore($exception));
    }

    public function test_that_underscore_returns_lowercase_underscored_name_from_string(): void
    {
        $expected = 'fight.common.domain.exception.runtime_exception';

        self::assertSame($expected, ClassName::underscore(RuntimeException::class));
    }

    public function test_that_short_returns_last_segment_from_object(): void
    {
        $exception = new RuntimeException('test');

        self::assertSame('RuntimeException', ClassName::short($exception));
    }

    public function test_that_short_returns_last_segment_from_string(): void
    {
        self::assertSame('RuntimeException', ClassName::short(RuntimeException::class));
    }

    public function test_that_short_returns_class_name_without_namespace(): void
    {
        self::assertSame('ClassName', ClassName::short(ClassName::class));
    }
}
