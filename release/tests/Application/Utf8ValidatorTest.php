<?php

declare(strict_types=1);

namespace Fight\Test\Release\Application;

use Fight\Release\Application\Utf8Validator;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Utf8Validator::class)]
/** Covers recursive UTF-8 validation for release value trees. */
final class Utf8ValidatorTest extends UnitTestCase
{
    /**
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_valid_utf8_value_trees_are_accepted(): void
    {
        self::assertTrue((new Utf8Validator())->isValid([
            'command' => 'inspect',
            'message' => 'Release café is ready.',
            'values'  => [1, true, null]
        ]));
    }

    /**
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_invalid_utf8_string_keys_and_values_are_rejected(): void
    {
        $validator = new Utf8Validator();

        self::assertFalse($validator->isValid(['message' => "invalid-\xFF"]));
        self::assertFalse($validator->isValid(["invalid-\xFF" => 'message']));
    }
}
