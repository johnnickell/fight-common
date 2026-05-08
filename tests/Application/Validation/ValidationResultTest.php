<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation;

use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Application\Validation\Data\ErrorData;
use Fight\Common\Application\Validation\ValidationResult;
use Fight\Common\Domain\Exception\MethodCallException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ValidationResult::class)]
class ValidationResultTest extends UnitTestCase
{
    public function test_that_passed_factory_returns_instance_where_is_passed_is_true(): void
    {
        $result = ValidationResult::passed(new ApplicationData([]));

        self::assertTrue($result->isPassed());
    }

    public function test_that_passed_factory_returns_instance_where_is_failed_is_false(): void
    {
        $result = ValidationResult::passed(new ApplicationData([]));

        self::assertFalse($result->isFailed());
    }

    public function test_that_get_data_returns_application_data_from_passed_result(): void
    {
        $applicationData = new ApplicationData(['name' => 'Alice']);
        $result = ValidationResult::passed($applicationData);

        self::assertSame($applicationData, $result->getData());
    }

    public function test_that_get_errors_throws_when_called_on_passed_result(): void
    {
        $result = ValidationResult::passed(new ApplicationData([]));

        $this->expectException(MethodCallException::class);
        $result->getErrors();
    }

    public function test_that_failed_factory_returns_instance_where_is_failed_is_true(): void
    {
        $result = ValidationResult::failed(new ErrorData([]));

        self::assertTrue($result->isFailed());
    }

    public function test_that_failed_factory_returns_instance_where_is_passed_is_false(): void
    {
        $result = ValidationResult::failed(new ErrorData([]));

        self::assertFalse($result->isPassed());
    }

    public function test_that_get_errors_returns_error_data_from_failed_result(): void
    {
        $errorData = new ErrorData(['name' => ['Required']]);
        $result = ValidationResult::failed($errorData);

        self::assertSame($errorData, $result->getErrors());
    }

    public function test_that_get_data_throws_when_called_on_failed_result(): void
    {
        $result = ValidationResult::failed(new ErrorData([]));

        $this->expectException(MethodCallException::class);
        $result->getData();
    }
}
