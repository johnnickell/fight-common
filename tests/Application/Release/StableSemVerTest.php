<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Release;

use Fight\Common\Application\Release\StableSemVer;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class StableSemVerTest
 *
 * Covers strict stable SemVer policy.
 */
#[CoversClass(StableSemVer::class)]
class StableSemVerTest extends UnitTestCase
{
    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

    /**
     * Covers strict exact increments without platform integer conversion.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_increment_is_strict_exact_and_safe_for_arbitrarily_large_identifiers(): void
    {
        self::assertSame('1.2.4', StableSemVer::increment('1.2.3', 'patch'));
        self::assertSame('1.3.0', StableSemVer::increment('1.2.3', 'minor'));
        self::assertSame('2.0.0', StableSemVer::increment('1.2.3', 'major'));
        self::assertSame(
            '100000000000000000000.0.0',
            StableSemVer::increment('99999999999999999999.8.7', 'major')
        );
        self::assertSame('1.100000000000000000000.0', StableSemVer::increment('1.99999999999999999999.7', 'minor'));
        self::assertSame('1.2.100000000000000000000', StableSemVer::increment('1.2.99999999999999999999', 'patch'));
        self::assertNull(StableSemVer::increment('01.2.3', 'patch'));
        self::assertNull(StableSemVer::increment('1.2.3', 'feature'));
        self::assertFalse(StableSemVer::isValid('1.2.3-alpha'));
    }

    /**
     * Covers canonical ordering without platform integer conversion.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_compare_orders_arbitrarily_large_canonical_stable_versions(): void
    {
        self::assertSame(0, StableSemVer::compare('1.2.3', '1.2.3'));
        self::assertSame(-1, StableSemVer::compare('1.2.3', '1.2.4'));
        self::assertSame(1, StableSemVer::compare('2.0.0', '1.999999999999999999999999.999999999999999999999999'));
        self::assertSame(
            1,
            StableSemVer::compare(
                '184467440737095516160.0.0',
                '18446744073709551616.999999999999999999999999.999999999999999999999999'
            )
        );
        self::assertNull(StableSemVer::compare('01.2.3', '1.2.3'));
        self::assertNull(StableSemVer::compare('1.2.3', '1.2'));
    }

    // phpcs:enable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
}
