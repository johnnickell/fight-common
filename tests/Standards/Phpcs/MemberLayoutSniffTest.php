<?php

declare(strict_types=1);

namespace Fight\Test\Common\Standards\Phpcs;

use Fight\Common\Standards\Phpcs\FightCommon\Sniffs\Classes\NamedClassMemberSpacingSniff;
use Fight\Common\Standards\Phpcs\FightCommon\Sniffs\Classes\NamedClassStructureSniff;
use Fight\Common\Standards\Phpcs\FightCommon\Sniffs\Classes\NamedMethodSpacingSniff;
use Fight\Common\Standards\Phpcs\FightCommon\Sniffs\Formatting\RequireVisibilityGroupSpacingSniff;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Util\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;

defined('PHP_CODESNIFFER_IN_TESTS') || define('PHP_CODESNIFFER_IN_TESTS', true);
defined('PHP_CODESNIFFER_CBF') || define('PHP_CODESNIFFER_CBF', false);
defined('PHP_CODESNIFFER_VERBOSITY') || define('PHP_CODESNIFFER_VERBOSITY', 0);
require_once dirname(__DIR__, 3).'/vendor/squizlabs/php_codesniffer/autoload.php';
class_exists(Tokens::class);

#[CoversClass(NamedClassMemberSpacingSniff::class)]
#[CoversClass(NamedClassStructureSniff::class)]
#[CoversClass(NamedMethodSpacingSniff::class)]
#[CoversClass(RequireVisibilityGroupSpacingSniff::class)]
final class MemberLayoutSniffTest extends UnitTestCase
{
    public function test_that_public_member_layout_sniffs_report_each_owned_violation(): void
    {
        $sources = $this->errorSources($this->processFixture('MemberLayout.noncompliant.inc'));

        self::assertSame([
            'FightCommon.Classes.NamedClassMemberSpacing.IncorrectCountOfBlankLinesBetweenMembers',
            'FightCommon.Classes.NamedClassStructure.IncorrectGroupOrder',
            'FightCommon.Classes.NamedClassStructure.IncorrectGroupOrder',
            'FightCommon.Classes.NamedClassStructure.IncorrectGroupOrder',
            'FightCommon.Classes.NamedMethodSpacing.IncorrectLinesCountBetweenMethods',
            'FightCommon.Formatting.RequireVisibilityGroupSpacing.MissingBlankLineBetweenVisibilityGroups',
            'FightCommon.Formatting.RequireVisibilityGroupSpacing.MissingBlankLineBetweenVisibilityGroups',
            'FightCommon.Formatting.RequireVisibilityGroupSpacing.UnexpectedBlankLineWithinVisibilityGroup',
        ], $sources);
    }

    public function test_that_public_member_layout_fixers_produce_exact_source_idempotently(): void
    {
        $file = $this->processFixture('MemberLayout.noncompliant.inc');

        self::assertTrue($file->fixer->fixFile());
        self::assertSame(file_get_contents(__DIR__.'/MemberLayout.fixed.inc'), $file->fixer->getContents());

        $fixed = $this->processSource($file->fixer->getContents());
        self::assertSame([], $this->errorSources($fixed));
        self::assertFalse($fixed->fixer->fixFile());
        self::assertSame($file->fixer->getContents(), $fixed->fixer->getContents());
    }

    public function test_that_public_member_layout_sniffs_accept_supported_named_type_forms(): void
    {
        $file = $this->processFixture('MemberLayout.compliant.inc');

        self::assertSame([], $this->errorSources($file));
        self::assertSame(0, $file->getFixableCount());
    }

    public function test_that_public_member_layout_sniffs_ignore_tests_and_anonymous_classes(): void
    {
        self::assertSame([], $this->errorSources($this->processFixture('MemberLayout.exclusions.inc')));
    }

    public function test_that_visibility_spacing_reports_without_fixing_intervening_content(): void
    {
        $file = $this->processFixture('MemberLayout.intervening-content.inc');

        self::assertSame(
            ['FightCommon.Formatting.RequireVisibilityGroupSpacing.UnexpectedBlankLineWithinVisibilityGroup'],
            $this->errorSources($file),
        );
        self::assertSame(0, $file->getFixableCount());
    }

    public function test_that_visibility_member_discovery_skips_nested_and_nonproperty_variables(): void
    {
        $sniff = new RequireVisibilityGroupSpacingSniff();
        $file = $this->mock(File::class);
        $file->shouldReceive('getTokens')->twice()->andReturn([
            0 => ['code' => T_CLASS, 'level' => 0, 'scope_opener' => 0, 'scope_closer' => 4],
            1 => ['code' => T_CONST, 'level' => 2],
            2 => ['code' => T_VARIABLE, 'level' => 1, 'conditions' => []],
            4 => ['code' => T_CLOSE_CURLY_BRACKET, 'level' => 0],
        ]);
        $file->shouldReceive('findNext')->times(3)->andReturn(1, 2, false);
        $file->shouldReceive('findPrevious')->once()->andReturn(1);
        $members = new \ReflectionMethod($sniff, 'members');

        self::assertSame([], $members->invoke($sniff, $file, 0));
    }

    public function test_that_visibility_member_start_includes_attached_comments_with_openers(): void
    {
        $sniff = new RequireVisibilityGroupSpacingSniff();
        $file = $this->mock(File::class);
        $tokens = [
            0 => ['code' => T_SEMICOLON, 'line' => 1],
            1 => ['code' => T_DOC_COMMENT_STRING, 'line' => 2, 'comment_opener' => 1],
            2 => ['code' => T_PUBLIC, 'line' => 3],
            3 => ['code' => T_VARIABLE, 'line' => 3],
        ];
        $file->shouldReceive('getTokens')->twice()->andReturn($tokens);
        $file->shouldReceive('findPrevious')->times(5)->andReturn(2, 0, 1, 0, 0);
        $memberStart = new \ReflectionMethod($sniff, 'memberStart');

        self::assertSame(1, $memberStart->invoke($sniff, $file, 3));
    }

    private function processFixture(string $name): LocalFile
    {
        return $this->processPath(__DIR__.'/'.$name);
    }

    private function processSource(string $source): LocalFile
    {
        $path = tempnam(sys_get_temp_dir(), 'member-layout-sniff-');
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
            '--standard='.$root.'/src/Standards/Phpcs/FightCommon/ruleset.xml',
            '--sniffs='.implode(',', [
                'FightCommon.Classes.NamedClassMemberSpacing',
                'FightCommon.Classes.NamedClassStructure',
                'FightCommon.Classes.NamedMethodSpacing',
                'FightCommon.Formatting.RequireVisibilityGroupSpacing',
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
