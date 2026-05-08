<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Exception;

use Fight\Common\Application\Validation\Exception\ValidationException;
use Fight\Common\Domain\Exception\ValidationException as BaseException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ValidationException::class)]
class ValidationExceptionTest extends UnitTestCase
{
    public function test_that_from_errors_creates_instance_of_validation_exception(): void
    {
        $exception = ValidationException::fromErrors(['field' => ['Required']]);

        self::assertInstanceOf(ValidationException::class, $exception);
    }

    public function test_that_get_errors_returns_the_errors_passed_to_from_errors(): void
    {
        $errors = ['email' => ['Invalid email'], 'name' => ['Required', 'Too short']];
        $exception = ValidationException::fromErrors($errors);

        self::assertSame($errors, $exception->getErrors());
    }

    public function test_that_exception_message_is_validation_failed(): void
    {
        $exception = ValidationException::fromErrors([]);

        self::assertSame('Validation Failed', $exception->getMessage());
    }

    public function test_that_exception_extends_base_validation_exception(): void
    {
        $exception = ValidationException::fromErrors([]);

        self::assertInstanceOf(BaseException::class, $exception);
    }
}
