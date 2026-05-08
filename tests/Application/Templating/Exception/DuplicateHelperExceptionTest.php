<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Templating\Exception;

use Fight\Common\Application\Templating\Exception\DuplicateHelperException;
use Fight\Common\Application\Templating\Exception\TemplatingException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DuplicateHelperException::class)]
class DuplicateHelperExceptionTest extends UnitTestCase
{
    public function test_that_construction_with_message_and_name_sets_both(): void
    {
        $exception = new DuplicateHelperException('Duplicate helper: date', 'date');

        self::assertSame('Duplicate helper: date', $exception->getMessage());
        self::assertSame('date', $exception->getName());
    }

    public function test_that_get_name_returns_null_when_no_name_provided(): void
    {
        $exception = new DuplicateHelperException('Duplicate helper');

        self::assertNull($exception->getName());
    }

    public function test_that_from_name_creates_instance_with_formatted_message_and_correct_name(): void
    {
        $exception = DuplicateHelperException::fromName('currency');

        self::assertSame('currency', $exception->getName());
        self::assertStringContainsString('currency', $exception->getMessage());
    }

    public function test_that_duplicate_helper_exception_extends_templating_exception(): void
    {
        $exception = new DuplicateHelperException('Duplicate helper: date', 'date');

        self::assertInstanceOf(TemplatingException::class, $exception);
    }
}
