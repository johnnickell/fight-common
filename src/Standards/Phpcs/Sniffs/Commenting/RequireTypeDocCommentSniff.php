<?php

declare(strict_types=1);

namespace Fight\Common\Standards\Phpcs\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Class RequireTypeDocCommentSniff
 */
final class RequireTypeDocCommentSniff implements Sniff
{
    public bool $strict = false;

    /**
     * Registers named type tokens for type documentation enforcement
     *
     * @return list<int>
     */
    public function register(): array
    {
        return [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];
    }

    /**
     * Enforces the canonical named-type summary
     */
    public function process(File $phpcsFile, int $stackPtr): void
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        if ($name === '' || str_ends_with($name, 'Test')) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $modifiers = [T_ABSTRACT, T_FINAL, T_READONLY];
        $comment = DocumentationComment::find($phpcsFile, $stackPtr, $modifiers);

        if (!$this->strict) {
            if ($tokens[$stackPtr]['code'] === T_CLASS || $comment !== null) {
                return;
            }

            $type = strtolower((string) $tokens[$stackPtr]['content']);
            $phpcsFile->addError(
                sprintf('Missing doc comment for %s %s', $type, $name),
                $stackPtr,
                'Missing'
            );

            return;
        }

        $expected = $this->typeLabel($tokens[$stackPtr]['code']).' '.$name;

        if ($comment === null) {
            if (
                $phpcsFile->addFixableError(
                    sprintf('Missing canonical doc comment for %s', $name),
                    $stackPtr,
                    'MissingDocComment'
                )
            ) {
                DocumentationComment::insert(
                    $phpcsFile,
                    DocumentationComment::declarationStart($phpcsFile, $stackPtr, $modifiers),
                    [$expected]
                );
            }

            return;
        }

        $lines = $comment['lines'];
        $first = DocumentationComment::firstContentLine($lines);
        $fix = false;

        if ($first === null) {
            $fix = $phpcsFile->addFixableError(
                sprintf('Expected "%s" as the type summary', $expected),
                $stackPtr,
                'IncorrectSummary'
            );
            $lines = [$expected];
        } else {
            $lines = array_slice($lines, $first);
            $summary = $lines[0];

            if ($summary === $expected) {
                // The summary is already canonical.
            } elseif (rtrim($summary, '.!?') === $expected) {
                $fix = $phpcsFile->addFixableError(
                    sprintf('Type summary must not end in punctuation: "%s"', $summary),
                    $stackPtr,
                    'TerminalPunctuation'
                );
                $lines[0] = $expected;
            } else {
                $fix = $phpcsFile->addFixableError(
                    sprintf('Expected "%s" as the type summary; found "%s"', $expected, $summary),
                    $stackPtr,
                    'IncorrectSummary'
                );
                $lines = array_merge([$expected, ''], $lines);
            }
        }

        $separated = DocumentationComment::normalizeSeparator($lines);

        if ($separated['changed']) {
            $fix = $phpcsFile->addFixableError(
                'Expected exactly one blank docblock line after the type summary',
                $stackPtr,
                'MissingBlankLine'
            ) || $fix;
            $lines = $separated['lines'];
        }

        if ($fix) {
            DocumentationComment::replace($phpcsFile, $comment, $lines);
        }
    }

    /**
     * Retrieves the canonical label for a type token
     */
    private function typeLabel(int $code): string
    {
        return match ($code) {
            T_INTERFACE => 'Interface',
            T_TRAIT => 'Trait',
            T_ENUM => 'Enum',
            default => 'Class',
        };
    }
}
