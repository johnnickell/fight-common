<?php

declare(strict_types=1);

namespace Fight\Test\Release\Tooling;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Class PackageSurfaceAuthorityTest
 */
#[CoversNothing]
final class PackageSurfaceAuthorityTest extends UnitTestCase
{
    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

    /**
     * Proves the intentional pre-certification Composer and exported-content boundary.
     */
    public function test_that_package_surface_defaults_and_content_roots_are_explicit(): void
    {
        $root = dirname(__DIR__, 3);
        $composer = json_decode(
            (string) file_get_contents($root.'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $normative = file_get_contents($root.'/release/README.md');
        self::assertIsString($normative);

        self::assertSame('src', $composer['autoload']['psr-4']["Fight\\Common\\"]);
        self::assertSame('tests/TestCase', $composer['autoload']['psr-4']["Fight\\Test\\Common\\TestCase\\"]);
        self::assertArrayNotHasKey('type', $composer);
        self::assertArrayNotHasKey('conflict', $composer);
        self::assertArrayNotHasKey('provide', $composer);
        self::assertArrayNotHasKey('replace', $composer);
        self::assertArrayNotHasKey('extra', $composer);
        self::assertArrayNotHasKey('archive', $composer);
        self::assertSame(
            ['dealerdirect/phpcodesniffer-composer-installer' => false],
            $composer['config']['allow-plugins']
        );
        self::assertStringContainsString("roots are `src` and `tests/TestCase`", $normative);
        self::assertStringContainsString('without certifying an exact archive', $normative);
    }
}
