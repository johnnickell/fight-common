<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Exception;

use Fight\Common\Domain\Exception\RuntimeException;
use Fight\Common\Domain\Exception\ValidationException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException as PhpRuntimeException;

#[CoversClass(ValidationException::class)]
class ValidationExceptionTest extends UnitTestCase
{
    public function test_that_get_errors_returns_the_errors_passed_to_constructor(): void
    {
        $errors = ['email' => ['Invalid email'], 'name' => ['Required']];
        $exception = new ValidationException($errors);

        self::assertSame($errors, $exception->getErrors());
    }

    public function test_that_default_message_is_used_when_none_is_provided(): void
    {
        $exception = new ValidationException([]);

        self::assertSame('Validation failed', $exception->getMessage());
    }

    public function test_that_custom_message_is_used_when_provided(): void
    {
        $exception = new ValidationException([], 'Custom message');

        self::assertSame('Custom message', $exception->getMessage());
    }

    public function test_that_previous_exception_is_forwarded(): void
    {
        $previous = new PhpRuntimeException('cause');
        $exception = new ValidationException([], null, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    public function test_that_exception_extends_domain_runtime_exception(): void
    {
        $exception = new ValidationException([]);

        self::assertInstanceOf(RuntimeException::class, $exception);
    }
}
