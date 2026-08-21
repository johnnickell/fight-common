<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Release;

use Fight\Common\Application\Release\CanonicalJson;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/** Covers canonical release JSON. */
#[CoversClass(CanonicalJson::class)]
class CanonicalJsonTest extends UnitTestCase
{
    /**
     * Covers recursive object-key ordering while retaining list order.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_encode_sorts_object_keys_recursively_without_reordering_lists(): void
    {
        $json = new CanonicalJson();

        self::assertSame(
            '{"a":{"a":1,"b":2},"z":[{"a":1,"b":2},3]}',
            $json->encode(['z' => [['b' => 2, 'a' => 1], 3], 'a' => ['b' => 2, 'a' => 1]])
        );
    }
}
