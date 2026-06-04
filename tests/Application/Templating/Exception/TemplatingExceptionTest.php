<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Templating\Exception;

use Exception;
use Fight\Common\Application\Templating\Exception\TemplatingException;
use Fight\Common\Domain\Exception\SystemException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TemplatingException::class)]
class TemplatingExceptionTest extends UnitTestCase
{
    public function test_that_construction_with_message_sets_message(): void
    {
        $exception = new TemplatingException('Something went wrong');

        self::assertSame('Something went wrong', $exception->getMessage());
    }

    public function test_that_construction_with_previous_exception_sets_previous(): void
    {
        $previous = new Exception('root cause');
        $exception = new TemplatingException('Wrapped', 0, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    public function test_that_templating_exception_extends_system_exception(): void
    {
        $exception = new TemplatingException('test');

        self::assertInstanceOf(SystemException::class, $exception);
    }
}
