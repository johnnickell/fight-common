<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Data;

use Fight\Common\Application\Validation\Data\ErrorData;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ErrorData::class)]
class ErrorDataTest extends UnitTestCase
{
    public function test_that_error_data_can_be_created_with_field_errors(): void
    {
        $data = new ErrorData(['name' => ['Required'], 'email' => ['Invalid email']]);

        self::assertSame(2, $data->count());
    }

    public function test_that_get_returns_errors_for_existing_field(): void
    {
        $data = new ErrorData(['name' => ['Required']]);

        self::assertSame(['Required'], $data->get('name'));
    }

    public function test_that_get_returns_empty_array_for_missing_field(): void
    {
        $data = new ErrorData(['name' => ['Required']]);

        self::assertSame([], $data->get('email'));
    }

    public function test_that_has_returns_true_for_existing_field(): void
    {
        $data = new ErrorData(['name' => ['Required']]);

        self::assertTrue($data->has('name'));
    }

    public function test_that_has_returns_false_for_missing_field(): void
    {
        $data = new ErrorData(['name' => ['Required']]);

        self::assertFalse($data->has('email'));
    }

    public function test_that_names_returns_all_field_names(): void
    {
        $data = new ErrorData(['name' => ['Required']]);

        self::assertSame(['name'], $data->names());
    }

    public function test_that_to_array_returns_the_correct_structure(): void
    {
        $data = new ErrorData(['name' => ['Required']]);

        self::assertSame(['name' => ['Required']], $data->toArray());
    }

    public function test_that_is_empty_returns_true_for_empty_data(): void
    {
        $data = new ErrorData([]);

        self::assertTrue($data->isEmpty());
    }

    public function test_that_is_empty_returns_false_when_items_exist(): void
    {
        $data = new ErrorData(['name' => ['Required']]);

        self::assertFalse($data->isEmpty());
    }

    public function test_that_iteration_visits_all_field_error_sets(): void
    {
        $data = new ErrorData(['name' => ['Required']]);

        $collected = [];
        foreach ($data as $field => $errors) {
            foreach ($errors as $message) {
                $collected[$field][] = $message;
            }
        }

        self::assertSame(['Required'], $collected['name']);
    }
}
