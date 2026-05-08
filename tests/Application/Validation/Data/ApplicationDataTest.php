<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Data;

use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ApplicationData::class)]
class ApplicationDataTest extends UnitTestCase
{
    public function test_that_application_data_can_be_created_with_key_value_pairs(): void
    {
        $data = new ApplicationData(['name' => 'Alice', 'age' => 30]);

        self::assertSame(2, $data->count());
    }

    public function test_that_get_returns_value_for_existing_key(): void
    {
        $data = new ApplicationData(['name' => 'Alice']);

        self::assertSame('Alice', $data->get('name'));
    }

    public function test_that_get_returns_default_for_missing_key(): void
    {
        $data = new ApplicationData(['name' => 'Alice']);

        self::assertSame('fallback', $data->get('email', 'fallback'));
    }

    public function test_that_get_returns_null_when_no_default_and_key_is_missing(): void
    {
        $data = new ApplicationData([]);

        self::assertNull($data->get('missing'));
    }

    public function test_that_has_returns_true_for_existing_key(): void
    {
        $data = new ApplicationData(['name' => 'Alice']);

        self::assertTrue($data->has('name'));
    }

    public function test_that_has_returns_false_for_missing_key(): void
    {
        $data = new ApplicationData(['name' => 'Alice']);

        self::assertFalse($data->has('email'));
    }

    public function test_that_names_returns_all_keys(): void
    {
        $data = new ApplicationData(['name' => 'Alice']);

        self::assertSame(['name'], $data->names());
    }

    public function test_that_to_array_returns_the_correct_array(): void
    {
        $data = new ApplicationData(['name' => 'Alice']);

        self::assertSame(['name' => 'Alice'], $data->toArray());
    }

    public function test_that_is_empty_returns_true_for_empty_data(): void
    {
        $data = new ApplicationData([]);

        self::assertTrue($data->isEmpty());
    }

    public function test_that_is_empty_returns_false_when_items_exist(): void
    {
        $data = new ApplicationData(['name' => 'Alice']);

        self::assertFalse($data->isEmpty());
    }

    public function test_that_iteration_visits_all_key_value_pairs(): void
    {
        $data = new ApplicationData(['name' => 'Alice']);

        $collected = [];
        foreach ($data as $key => $value) {
            $collected[$key] = $value;
        }

        self::assertSame('Alice', $collected['name']);
    }
}
