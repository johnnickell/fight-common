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
            [
                'dealerdirect/phpcodesniffer-composer-installer' => false,
                'yiisoft/config'                                 => true
            ],
            $composer['config']['allow-plugins']
        );
        self::assertArrayNotHasKey('yiisoft/mailer', $composer['require']);
        self::assertSame('^6.1', $composer['require-dev']['yiisoft/mailer']);
        self::assertSame(
            'Required to evaluate Yii Mail against the shared Fight mail conformance suite',
            $composer['suggest']['yiisoft/mailer']
        );
        self::assertArrayNotHasKey('yiisoft/view', $composer['require']);
        self::assertSame('^12.2', $composer['require-dev']['yiisoft/view']);
        self::assertSame(
            'Required to evaluate Yii View against the shared Fight templating conformance suite',
            $composer['suggest']['yiisoft/view']
        );
        self::assertArrayNotHasKey('yiisoft/files', $composer['require']);
        self::assertSame('^2.0', $composer['require-dev']['yiisoft/files']);
        self::assertSame(
            'Required to evaluate Yii Files against the shared Fight filesystem conformance suite',
            $composer['suggest']['yiisoft/files']
        );
        self::assertStringContainsString("roots are `src` and `tests/TestCase`", $normative);
        self::assertStringContainsString('without certifying an exact archive', $normative);
    }

    /**
     * Proves optional framework requirements do not leak into production.
     */
    public function test_that_framework_and_provider_dependencies_are_development_only_and_suggested(): void
    {
        $root = dirname(__DIR__, 3);
        $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $frameworks = [
            'symfony/dependency-injection', 'laravel/framework', 'yiisoft/di', 'codeigniter4/framework', 'slim/slim'
        ];

        foreach ($frameworks as $package) {
            self::assertArrayNotHasKey($package, $composer['require']);
            self::assertArrayHasKey($package, $composer['require-dev']);
            self::assertArrayHasKey($package, $composer['suggest']);
            self::assertNotSame('', $composer['suggest'][$package]);
        }
    }
}
