<?php

declare(strict_types=1);

namespace Fight\Common\Standards\Phpcs\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;

/**
 * Class DocumentationComment
 */
final class DocumentationComment
{
    /**
     * Finds and parses the docblock attached to a declaration
     *
     * @param File $phpcsFile The file being scanned
     * @param integer $stackPtr The declaration token pointer
     * @param list<int|string> $modifiers Declaration modifiers to step over
     *
     * @return array{start: int, end: int, lines: list<string>}|null
     */
    public static function find(File $phpcsFile, int $stackPtr, array $modifiers = []): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $cursor = $stackPtr;

        while (true) {
            $previous = $phpcsFile->findPrevious(T_WHITESPACE, $cursor - 1, null, true);
            $previous = (int) $previous;

            if (in_array($tokens[$previous]['code'], $modifiers, true)) {
                $cursor = $previous;
                continue;
            }

            if ($tokens[$previous]['code'] === T_ATTRIBUTE_END) {
                $cursor = (int) $tokens[$previous]['attribute_opener'];
                continue;
            }

            if ($tokens[$previous]['code'] !== T_DOC_COMMENT_CLOSE_TAG) {
                return null;
            }

            $start = (int) $tokens[$previous]['comment_opener'];

            return [
                'start' => $start,
                'end'   => $previous,
                'lines' => self::lines($phpcsFile, $start, $previous)
            ];
        }
    }

    /**
     * Finds the earliest modifier or attribute belonging to a declaration
     *
     * @param File $phpcsFile The file being scanned
     * @param integer $stackPtr The declaration token pointer
     * @param list<int|string> $modifiers Declaration modifiers to step over
     */
    public static function declarationStart(File $phpcsFile, int $stackPtr, array $modifiers): int
    {
        $tokens = $phpcsFile->getTokens();
        $start = $stackPtr;

        while (true) {
            $previous = (int) $phpcsFile->findPrevious(T_WHITESPACE, $start - 1, null, true);

            if (in_array($tokens[$previous]['code'], $modifiers, true)) {
                $start = $previous;
                continue;
            }

            if ($tokens[$previous]['code'] === T_ATTRIBUTE_END) {
                $opener = $tokens[$previous]['attribute_opener'] ?? null;

                if (is_int($opener)) {
                    $start = $opener;
                    continue;
                }
            }

            return $start;
        }
    }

    /**
     * Inserts a canonical docblock immediately before a declaration
     *
     * @param File $phpcsFile The file being fixed
     * @param integer $stackPtr The declaration token pointer
     * @param array $lines Normalized docblock lines
     *
     * @phpstan-param list<string> $lines
     */
    public static function insert(File $phpcsFile, int $stackPtr, array $lines): void
    {
        $tokens = $phpcsFile->getTokens();
        $indent = str_repeat(' ', max(0, ((int) $tokens[$stackPtr]['column']) - 1));
        $content = self::render($lines, $indent, $phpcsFile->eolChar).$phpcsFile->eolChar.$indent;

        $phpcsFile->fixer->addContentBefore($stackPtr, $content);
    }

    /**
     * Replaces a complete docblock while retaining its declaration indentation
     *
     * @param File $phpcsFile The file being fixed
     * @param array{start: int, end: int, lines: list<string>} $comment Parsed docblock details
     * @param array $lines Normalized docblock lines
     *
     * @phpstan-param list<string> $lines
     */
    public static function replace(File $phpcsFile, array $comment, array $lines): void
    {
        $tokens = $phpcsFile->getTokens();
        $indent = str_repeat(' ', max(0, ((int) $tokens[$comment['start']]['column']) - 1));
        $replacement = self::render($lines, $indent, $phpcsFile->eolChar);

        $phpcsFile->fixer->beginChangeset();
        $phpcsFile->fixer->replaceToken($comment['start'], $replacement);

        for ($pointer = $comment['start'] + 1; $pointer <= $comment['end']; $pointer++) {
            $phpcsFile->fixer->replaceToken($pointer, '');
        }

        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Finds the first non-empty normalized docblock line
     *
     * @param array $lines Normalized docblock lines
     *
     * @phpstan-param list<string> $lines
     */
    public static function firstContentLine(array $lines): ?int
    {
        foreach ($lines as $index => $line) {
            if ($line !== '') {
                return $index;
            }
        }

        return null;
    }

    /**
     * Normalizes the separator after a docblock summary
     *
     * @param array $lines Normalized docblock lines
     *
     * @return array{lines: list<string>, changed: bool}
     *
     * @phpstan-param list<string> $lines
     */
    public static function normalizeSeparator(array $lines): array
    {
        if (count($lines) < 2) {
            return ['lines' => $lines, 'changed' => false];
        }

        $remainder = array_slice($lines, 1);

        while ($remainder !== [] && $remainder[0] === '') {
            array_shift($remainder);
        }

        $normalized = array_merge([$lines[0], ''], $remainder);

        return ['lines' => $normalized, 'changed' => $normalized !== $lines];
    }

    /**
     * Parses normalized lines from a complete docblock
     *
     * @return list<string>
     */
    private static function lines(File $phpcsFile, int $start, int $end): array
    {
        $raw = $phpcsFile->getTokensAsString($start, ($end - $start) + 1);

        if (!str_contains($raw, "\n") && !str_contains($raw, "\r")) {
            $content = preg_replace('/^\/\*\*\s*|\s*\*\/$/', '', $raw);

            return is_string($content) && $content !== '' ? [$content] : [];
        }

        $rawLines = (array) preg_split('/\R/', $raw);
        $lines = [];

        foreach (array_slice($rawLines, 1, -1) as $line) {
            $content = preg_replace('/^\s*\* ?/', '', $line);
            $lines[] = rtrim(is_string($content) ? $content : '');
        }

        while ($lines !== [] && $lines[0] === '') {
            array_shift($lines);
        }

        while ($lines !== [] && $lines[array_key_last($lines)] === '') {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * Renders normalized lines as an indented docblock
     *
     * @param array $lines Normalized docblock lines
     *
     * @phpstan-param list<string> $lines
     */
    private static function render(array $lines, string $indent, string $eol): string
    {
        $rendered = '/**'.$eol;

        foreach ($lines as $line) {
            $rendered .= $indent.' *'.($line === '' ? '' : ' '.$line).$eol;
        }

        return $rendered.$indent.' */';
    }
}
