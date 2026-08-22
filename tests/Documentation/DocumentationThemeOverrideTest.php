<?php

declare(strict_types=1);

namespace Fight\Test\Common\Documentation;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class DocumentationThemeOverrideTest extends UnitTestCase
{
    public function test_that_the_not_found_override_is_owned_by_documentation(): void
    {
        $root = dirname(__DIR__, 2);
        $configuration = file_get_contents($root.'/mkdocs.yml');
        $override = file_get_contents($root.'/docs/overrides/404.html');
        $contributing = file_get_contents($root.'/docs/contributing.md');

        self::assertIsString($configuration);
        self::assertIsString($override);
        self::assertIsString($contributing);
        self::assertStringContainsString('  custom_dir: docs/overrides', $configuration);
        self::assertStringContainsString("exclude_docs: |\n  /overrides/", $configuration);
        self::assertDirectoryDoesNotExist($root.'/overrides');
        self::assertFileDoesNotExist($root.'/overrides/404.html');
        self::assertStringContainsString('{% extends "main.html" %}', $override);
        self::assertStringContainsString('<h1>Page not found</h1>', $override);
        self::assertStringContainsString('Return to the documentation home', $override);
        self::assertStringContainsString(
            'https://github.com/johnnickell/fight-common/blob/develop/release/README.md',
            $contributing,
        );
    }
}
