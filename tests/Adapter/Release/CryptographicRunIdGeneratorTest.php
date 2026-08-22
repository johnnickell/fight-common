<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Release;

use Fight\Common\Adapter\Release\CryptographicRunIdGenerator;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
/**
 * Class CryptographicRunIdGeneratorTest
 *
 * Covers operating-system-backed release-run identities.
 */
#[CoversClass(CryptographicRunIdGenerator::class)]
final class CryptographicRunIdGeneratorTest extends UnitTestCase
{
    /**
     * Asserts each invocation creates a distinct lowercase SHA-256-width identity
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_generate_returns_a_distinct_well_formed_identity(): void
    {
        $generator = new CryptographicRunIdGenerator();
        $first = $generator->generate();
        $second = $generator->generate();

        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/D', $first);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/D', $second);
        self::assertNotSame($first, $second);
    }
}
