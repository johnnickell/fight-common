<?php

declare(strict_types=1);

namespace Fight\Test\Common\Documentation;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class FrameworkSupportGuideTest extends UnitTestCase
{
    public function test_that_the_framework_support_contract_is_published_at_every_consumer_entry_point(): void
    {
        $root = dirname(__DIR__, 2);
        $guide = file_get_contents($root.'/docs/framework-support.md');
        $index = file_get_contents($root.'/docs/README.md');
        $mkdocs = file_get_contents($root.'/mkdocs.yml');
        $composer = file_get_contents($root.'/composer.json');

        self::assertIsString($guide);
        self::assertIsString($index);
        self::assertIsString($mkdocs);
        self::assertIsString($composer);

        foreach ([
            '# Framework support and activation',
            'Symfony components `^8.1`',
            'Laravel `^13.0`',
            'CodeIgniter `^4.7`',
            'Slim `^4.15`',
            'current Yii 3 package set',
            'PHP 8.6',
            'never exceeds two maintained majors',
            '**ship**',
            '**prototype**',
            '**wire**',
            'Stable Yii Queue is unavailable for 1.2',
            'at-least-once',
            'Fight AccessControl',
            'PSR-6',
            'reserved for 2.0',
        ] as $requiredContract) {
            self::assertStringContainsString($requiredContract, $guide);
        }

        self::assertStringContainsString('[Framework support and activation](framework-support.md)', $index);
        self::assertStringContainsString('Framework Support: framework-support.md', $mkdocs);

        foreach ([
            'codeigniter4/framework',
            'codeigniter4/queue',
            'laravel/framework',
            'slim/slim',
            'symfony/messenger',
            'yiisoft/config',
            'yiisoft/di',
            'yiisoft/router',
        ] as $optionalPackage) {
            self::assertStringContainsString('"'.$optionalPackage.'"', $composer);
            self::assertStringContainsString('`'.$optionalPackage.'`', $guide);
        }

        self::assertDoesNotMatchRegularExpression('/"require"\s*:\s*\{[^}]*"(?:laravel\/framework|codeigniter4\/framework|slim\/slim|symfony\/messenger|yiisoft\/di)"/s', $composer);
    }
}
