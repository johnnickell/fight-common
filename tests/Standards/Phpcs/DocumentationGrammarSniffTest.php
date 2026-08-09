<?php

declare(strict_types=1);

namespace Fight\Test\Common\Standards\Phpcs;

use Fight\Common\Standards\Phpcs\FightCommon\Sniffs\Commenting\DocumentationComment;
use Fight\Common\Standards\Phpcs\FightCommon\Sniffs\Commenting\RequireMethodDocCommentSniff;
use Fight\Common\Standards\Phpcs\FightCommon\Sniffs\Commenting\RequireTypeDocCommentSniff;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Util\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;

defined('PHP_CODESNIFFER_IN_TESTS') || define('PHP_CODESNIFFER_IN_TESTS', true);
defined('PHP_CODESNIFFER_CBF') || define('PHP_CODESNIFFER_CBF', false);
defined('PHP_CODESNIFFER_VERBOSITY') || define('PHP_CODESNIFFER_VERBOSITY', 0);
require_once dirname(__DIR__, 3).'/vendor/squizlabs/php_codesniffer/autoload.php';
class_exists(Tokens::class);

#[CoversClass(DocumentationComment::class)]
#[CoversClass(RequireMethodDocCommentSniff::class)]
#[CoversClass(RequireTypeDocCommentSniff::class)]
final class DocumentationGrammarSniffTest extends UnitTestCase
{
    public function test_that_public_documentation_sniffs_report_every_owned_violation(): void
    {
        $file = $this->processFixture('DocumentationGrammar.noncompliant.inc');

        self::assertSame([
            'FightCommon.Commenting.RequireMethodDocComment.AmbiguousSummary',
            'FightCommon.Commenting.RequireMethodDocComment.InheritDocWithContent',
            'FightCommon.Commenting.RequireMethodDocComment.InvalidConstructorSummary',
            'FightCommon.Commenting.RequireMethodDocComment.MissingBlankLine',
            'FightCommon.Commenting.RequireMethodDocComment.MissingDocComment',
            'FightCommon.Commenting.RequireMethodDocComment.TerminalPunctuation',
            'FightCommon.Commenting.RequireMethodDocComment.UnapprovedVerb',
            'FightCommon.Commenting.RequireMethodDocComment.WrappedSummary',
            'FightCommon.Commenting.RequireTypeDocComment.IncorrectSummary',
            'FightCommon.Commenting.RequireTypeDocComment.MissingBlankLine',
            'FightCommon.Commenting.RequireTypeDocComment.MissingDocComment',
            'FightCommon.Commenting.RequireTypeDocComment.TerminalPunctuation',
        ], array_values(array_unique($this->errorSources($file))));
    }

    public function test_that_public_documentation_fixers_produce_exact_source_idempotently(): void
    {
        $file = $this->processFixture('DocumentationGrammar.noncompliant.inc');

        self::assertTrue($file->fixer->fixFile());
        self::assertSame(
            file_get_contents(__DIR__.'/DocumentationGrammar.fixed.inc'),
            $file->fixer->getContents(),
        );

        $fixed = $this->processSource($file->fixer->getContents());
        self::assertSame(0, $fixed->getFixableCount(), implode(', ', $this->errorSources($fixed)));
        self::assertFalse($fixed->fixer->fixFile());
        self::assertSame($file->fixer->getContents(), $fixed->fixer->getContents());
    }

    public function test_that_public_documentation_sniffs_accept_supported_declarations_and_exclusions(): void
    {
        $file = $this->processFixture('DocumentationGrammar.compliant.inc');

        self::assertSame([], $this->errorSources($file));
        self::assertSame(0, $file->getFixableCount());
    }

    public function test_that_strict_type_grammar_normalizes_existing_legacy_prose(): void
    {
        $sources = $this->errorSources($this->processFixture('DocumentationGrammar.legacy.inc'));

        self::assertContains('FightCommon.Commenting.RequireTypeDocComment.IncorrectSummary', $sources);
        self::assertContains('FightCommon.Commenting.RequireTypeDocComment.MissingDocComment', $sources);
    }

    public function test_that_lenient_type_grammar_requires_docs_only_for_interfaces_traits_and_enums(): void
    {
        $file = $this->processFixture('DocumentationGrammar.lenient.inc', strictTypes: false);
        $sources = array_values(array_filter(
            $this->errorSources($file),
            static fn (string $source): bool => str_starts_with(
                $source,
                'FightCommon.Commenting.RequireTypeDocComment.',
            ),
        ));

        self::assertSame([
            'FightCommon.Commenting.RequireTypeDocComment.Missing',
            'FightCommon.Commenting.RequireTypeDocComment.Missing',
            'FightCommon.Commenting.RequireTypeDocComment.Missing',
        ], $sources);
        self::assertSame(0, $file->getFixableCount());
    }

    private function processFixture(string $name, bool $strictTypes = true): LocalFile
    {
        return $this->processPath(__DIR__.'/'.$name, $strictTypes);
    }

    private function processSource(string $source): LocalFile
    {
        $path = tempnam(sys_get_temp_dir(), 'documentation-grammar-');
        self::assertIsString($path);
        file_put_contents($path, $source);

        try {
            return $this->processPath($path, true);
        } finally {
            unlink($path);
        }
    }

    private function processPath(string $path, bool $strictTypes): LocalFile
    {
        $root = dirname(__DIR__, 3);
        $config = new Config([
            '--standard='.$root.'/src/Standards/Phpcs/FightCommon/ruleset.xml',
            '--sniffs='.implode(',', [
                'FightCommon.Commenting.RequireMethodDocComment',
                'FightCommon.Commenting.RequireTypeDocComment',
            ]),
        ]);
        $config->cache = false;
        $config->tabWidth = 0;
        $ruleset = new Ruleset($config);

        foreach ($ruleset->sniffs as $sniff) {
            if ($sniff instanceof RequireTypeDocCommentSniff) {
                $sniff->strict = $strictTypes;
            }
        }

        $file = new LocalFile($path, $ruleset, $config);
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
