<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Data;

use Fight\Common\Application\Validation\Data\InputData;
use Fight\Common\Domain\Exception\KeyException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InputData::class)]
class InputDataTest extends UnitTestCase
{
    public function test_that_input_data_can_be_created_with_key_value_pairs(): void
    {
        $data = new InputData(['name' => 'Alice', 'age' => 30]);

        self::assertSame(2, $data->count());
    }

    public function test_that_get_returns_value_for_existing_key(): void
    {
        $data = new InputData(['name' => 'Alice']);

        self::assertSame('Alice', $data->get('name'));
    }

    public function test_that_get_throws_for_missing_key(): void
    {
        $data = new InputData(['name' => 'Alice']);

        $this->expectException(KeyException::class);
        $data->get('email');
    }

    public function test_that_has_returns_true_for_existing_key(): void
    {
        $data = new InputData(['name' => 'Alice']);

        self::assertTrue($data->has('name'));
    }

    public function test_that_has_returns_false_for_missing_key(): void
    {
        $data = new InputData(['name' => 'Alice']);

        self::assertFalse($data->has('email'));
    }

    public function test_that_count_returns_correct_count(): void
    {
        $data = new InputData(['a' => 1, 'b' => 2, 'c' => 3]);

        self::assertSame(3, $data->count());
    }

    public function test_that_is_empty_returns_true_for_empty_data(): void
    {
        $data = new InputData([]);

        self::assertTrue($data->isEmpty());
    }

    public function test_that_is_empty_returns_false_when_items_exist(): void
    {
        $data = new InputData(['name' => 'Alice']);

        self::assertFalse($data->isEmpty());
    }

    public function test_that_to_array_returns_the_original_array(): void
    {
        $data = new InputData(['name' => 'Alice']);

        self::assertSame(['name' => 'Alice'], $data->toArray());
    }

    public function test_that_iteration_visits_all_key_value_pairs(): void
    {
        $data = new InputData(['name' => 'Alice']);

        $collected = [];
        foreach ($data as $key => $value) {
            $collected[$key] = $value;
        }

        self::assertSame('Alice', $collected['name']);
    }
}
