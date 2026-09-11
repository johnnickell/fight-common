<?php

declare(strict_types=1);

namespace Fight\Test\Common\Standards\Phpcs;

use Fight\Common\Standards\Phpcs\Sniffs\Arrays\DisallowTrailingArrayCommaSniff;
use Fight\Common\Standards\Phpcs\Sniffs\Arrays\RequireAlignedArrayArrowSniff;
use Fight\Common\Standards\Phpcs\Sniffs\Files\RequireStrictTypesSniff;
use Fight\Common\Standards\Phpcs\Sniffs\Formatting\RequireBlankLineBeforeReturnSniff;
use Fight\Common\Standards\Phpcs\Sniffs\NamingConventions\RequireUppercaseUnderscoredEnumCaseSniff;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Fixer;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Util\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;

defined('PHP_CODESNIFFER_IN_TESTS') || define('PHP_CODESNIFFER_IN_TESTS', true);
defined('PHP_CODESNIFFER_CBF') || define('PHP_CODESNIFFER_CBF', false);
defined('PHP_CODESNIFFER_VERBOSITY') || define('PHP_CODESNIFFER_VERBOSITY', 0);
require_once dirname(__DIR__, 3).'/vendor/squizlabs/php_codesniffer/autoload.php';
class_exists(Tokens::class);

#[CoversClass(DisallowTrailingArrayCommaSniff::class)]
#[CoversClass(RequireAlignedArrayArrowSniff::class)]
#[CoversClass(RequireBlankLineBeforeReturnSniff::class)]
#[CoversClass(RequireStrictTypesSniff::class)]
#[CoversClass(RequireUppercaseUnderscoredEnumCaseSniff::class)]
final class CustomSniffTest extends UnitTestCase
{
    public function test_that_trailing_comma_ignores_incomplete_token_contexts(): void
    {
        $sniff = new DisallowTrailingArrayCommaSniff();
        $file = $this->mock(File::class);
        $file->shouldReceive('getTokens')->twice()->andReturn(
            [1 => ['code' => T_COMMA]],
            [1 => ['code' => T_COMMA], 2 => ['code' => T_CLOSE_PARENTHESIS]],
        );
        $file->shouldReceive('findNext')->twice()->andReturn(false, 2);

        $sniff->process($file, 1);
        $sniff->process($file, 1);

        self::assertSame([T_COMMA], $sniff->register());
    }

    public function test_that_array_alignment_handles_incomplete_and_legacy_array_tokens(): void
    {
        $sniff = new RequireAlignedArrayArrowSniff();
        $file = $this->mock(File::class);
        $file->shouldReceive('getTokens')->times(3)->andReturn(
            [0 => ['code' => T_OPEN_SHORT_ARRAY, 'line' => 1]],
            [
                0 => ['code' => T_OPEN_SHORT_ARRAY, 'bracket_closer' => 2, 'line' => 1],
                1 => ['code' => T_DOUBLE_ARROW, 'column' => 3, 'line' => 2],
                2 => ['code' => T_CLOSE_SHORT_ARRAY, 'line' => 3],
            ],
            [
                0 => ['code' => T_ARRAY, 'line' => 1],
                1 => ['code' => T_OPEN_PARENTHESIS, 'parenthesis_closer' => 2, 'line' => 1],
                2 => ['code' => T_CLOSE_PARENTHESIS, 'line' => 1],
            ],
        );
        $file->shouldReceive('findPrevious')->once()->andReturnFalse();
        $file->shouldReceive('findNext')->once()->with(T_OPEN_PARENTHESIS, 1)->andReturn(1);

        $sniff->process($file, 0);
        $sniff->process($file, 0);
        $sniff->process($file, 0);

        self::assertSame([T_OPEN_SHORT_ARRAY, T_ARRAY], $sniff->register());
    }

    public function test_that_array_alignment_ignores_an_arrow_without_a_key_in_incomplete_source(): void
    {
        $file = $this->processSource("<?php\n\ndeclare(strict_types=1);\n\n[\n    => 1\n];\n");

        self::assertSame([], $this->errorSources($file));
        self::assertSame(0, $file->getFixableCount());
    }

    public function test_that_array_alignment_fixes_existing_whitespace_padding(): void
    {
        $sniff = new RequireAlignedArrayArrowSniff();
        $file = $this->mock(File::class);
        $fixer = $this->mock(Fixer::class);
        $file->fixer = $fixer;
        $file->shouldReceive('getTokens')->andReturn([
            0 => ['code' => T_OPEN_SHORT_ARRAY, 'bracket_closer' => 6, 'line' => 1],
            1 => ['code' => T_CONSTANT_ENCAPSED_STRING, 'column' => 5, 'content' => "'a'", 'line' => 2],
            2 => ['code' => T_WHITESPACE, 'content' => ' ', 'line' => 2],
            3 => ['code' => T_DOUBLE_ARROW, 'column' => 9, 'content' => '=>', 'line' => 2],
            4 => ['code' => T_CONSTANT_ENCAPSED_STRING, 'column' => 5, 'content' => "'long'", 'line' => 3],
            5 => ['code' => T_DOUBLE_ARROW, 'column' => 11, 'content' => '=>', 'line' => 3],
            6 => ['code' => T_CLOSE_SHORT_ARRAY, 'line' => 4],
        ]);
        $file->shouldReceive('findPrevious')->twice()->andReturn(1, 4);
        $file->shouldReceive('addFixableError')->twice()->andReturn(true, false);
        $fixer->shouldReceive('replaceToken')->once()->with(2, '    ');

        $sniff->process($file, 0);
    }

    public function test_that_array_alignment_defensively_ignores_nonpositive_padding(): void
    {
        $sniff = new RequireAlignedArrayArrowSniff();
        $file = $this->mock(File::class);
        $method = new \ReflectionMethod($sniff, 'fixArrowAlignment');

        $method->invoke($sniff, $file, [], ['arrowPtr' => 2, 'keyEnd' => 5], 5);

        self::assertTrue(true);
    }

    public function test_that_blank_line_before_return_reports_and_fixes_the_adjacent_case(): void
    {
        $sniff = new RequireBlankLineBeforeReturnSniff();
        $file = $this->mock(File::class);
        $fixer = $this->mock(Fixer::class);
        $file->fixer = $fixer;
        $file->shouldReceive('getTokens')->andReturn([
            1 => ['code' => T_STRING, 'line' => 10],
            2 => ['code' => T_WHITESPACE, 'line' => 11],
            3 => ['code' => T_RETURN, 'line' => 11],
        ]);
        $file->shouldReceive('findPrevious')->times(3)->andReturn(false, 1, 1);
        $file->shouldReceive('addFixableError')
            ->once()
            ->with('Expected a blank line before the return statement', 3, 'Missing')
            ->andReturnTrue();
        $fixer->shouldReceive('addNewlineBefore')->once()->with(2);

        $sniff->process($file, 3);
        $sniff->process($file, 3);

        self::assertSame([T_RETURN], $sniff->register());
    }

    public function test_that_public_sniffs_report_fix_and_then_accept_real_source_edges(): void
    {
        $file = $this->processFixture('CustomSniffEdge.inc');

        self::assertSame(
            [
                'Phpcs.Arrays.DisallowTrailingArrayComma.DisallowTrailingArrayComma' => 7,
                'Phpcs.Arrays.RequireAlignedArrayArrow.ArrowNotAligned' => 5,
            ],
            array_count_values($this->errorSources($file)),
        );
        self::assertTrue($file->fixer->fixFile());
        self::assertSame(
            file_get_contents(__DIR__.'/CustomSniffEdge.fixed.inc'),
            $file->fixer->getContents(),
        );

        $fixed = $this->processSource($file->fixer->getContents());

        self::assertSame([], $this->errorSources($fixed));
        self::assertFalse($fixed->fixer->fixFile());
    }

    public function test_that_public_sniffs_accept_nested_indexed_and_compact_array_forms(): void
    {
        $file = $this->processFixture('CustomSniffEdge.compliant.inc');

        self::assertSame([], $this->errorSources($file));
        self::assertSame(0, $file->getFixableCount());
    }

    public function test_that_strict_types_fixes_a_missing_declaration_canonically_and_idempotently(): void
    {
        $file = $this->processSource("<?php\n\nnamespace Example;\n");

        self::assertContains('Phpcs.Files.RequireStrictTypes.Missing', $this->errorSources($file));
        self::assertTrue($file->fixer->fixFile());
        self::assertSame(
            "<?php\n\ndeclare(strict_types=1);\n\nnamespace Example;\n",
            $file->fixer->getContents(),
        );

        $fixed = $this->processSource($file->fixer->getContents());

        self::assertSame([], $this->errorSources($fixed));
        self::assertFalse($fixed->fixer->fixFile());
    }

    public function test_that_blank_line_before_return_preserves_indentation_and_is_idempotent(): void
    {
        $file = $this->processSource(
            "<?php\n\ndeclare(strict_types=1);\n\nfunction answer(): int\n"
            ."{\n    \$value = 1;\n    return \$value;\n}\n",
        );

        self::assertContains(
            'Phpcs.Formatting.RequireBlankLineBeforeReturn.Missing',
            $this->errorSources($file),
        );
        self::assertTrue($file->fixer->fixFile());
        self::assertSame(
            "<?php\n\ndeclare(strict_types=1);\n\nfunction answer(): int\n"
            ."{\n    \$value = 1;\n\n    return \$value;\n}\n",
            $file->fixer->getContents(),
        );

        $fixed = $this->processSource($file->fixer->getContents());

        self::assertSame([], $this->errorSources($fixed));
        self::assertFalse($fixed->fixer->fixFile());
    }

    public function test_that_strict_types_only_evaluates_the_first_open_tag_and_all_declarations(): void
    {
        $source = "<?php declare(ticks=1); declare(strict_types=1); ?>\n<?php return 1;";

        self::assertSame([], $this->errorSources($this->processSource($source)));
    }

    public function test_that_enum_cases_require_uppercase_underscored_names(): void
    {
        $source = "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Enum State\n */\nenum State\n"
            ."{\n    case Ready;\n    case HTTP_OK;\n}\n";
        $file = $this->processSource($source);

        self::assertSame(
            ['Phpcs.NamingConventions.RequireUppercaseUnderscoredEnumCase.NotUppercaseUnderscored'],
            $this->errorSources($file)
        );
        self::assertSame(0, $file->getFixableCount());
    }

    public function test_that_enum_case_naming_ignores_incomplete_token_context(): void
    {
        $sniff = new RequireUppercaseUnderscoredEnumCaseSniff();
        $file = $this->mock(File::class);
        $file->shouldReceive('getTokens')->once()->andReturn([]);
        $file->shouldReceive('findNext')->once()->with(T_STRING, 2)->andReturnFalse();

        $sniff->process($file, 1);

        self::assertSame([T_ENUM_CASE], $sniff->register());
    }

    private function processFixture(string $name): LocalFile
    {
        return $this->processPath(__DIR__.'/'.$name);
    }

    private function processSource(string $source): LocalFile
    {
        $path = tempnam(sys_get_temp_dir(), 'fight-common-sniff-');
        self::assertIsString($path);
        file_put_contents($path, $source);

        try {
            return $this->processPath($path);
        } finally {
            unlink($path);
        }
    }

    private function processPath(string $path): LocalFile
    {
        $root = dirname(__DIR__, 3);
        $config = new Config([
            '--standard='.$root.'/src/Standards/Phpcs/ruleset.xml',
            '--sniffs='.implode(',', [
                'Phpcs.Arrays.DisallowTrailingArrayComma',
                'Phpcs.Arrays.RequireAlignedArrayArrow',
                'Phpcs.Files.RequireStrictTypes',
                'Phpcs.Formatting.RequireBlankLineBeforeReturn',
                'Phpcs.NamingConventions.RequireUppercaseUnderscoredEnumCase',
            ]),
        ]);
        $config->cache = false;
        $config->tabWidth = 0;
        $file = new LocalFile($path, new Ruleset($config), $config);
        $file->process();

        return $file;
    }

    /** @return list<string> */
    private function errorSources(LocalFile $file): array
    {
        $sources = [];

        foreach ($file->getErrors() as $lineErrors) {
            foreach ($lineErrors as $columnErrors) {
                foreach ($columnErrors as $error) {
                    $sources[] = $error['source'];
                }
            }
        }

        sort($sources);

        return $sources;
    }
}
