<?php

declare(strict_types=1);

namespace Fight\Common\Standards\Phpcs\FightCommon\Sniffs\Arrays;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Class RequireAlignedArrayArrowSniff
 */
final class RequireAlignedArrayArrowSniff implements Sniff
{
    /**
     * Registers the tokens that this sniff wants to listen for
     *
     * @return list<int|string>
     */
    public function register(): array
    {
        return [T_OPEN_SHORT_ARRAY, T_ARRAY];
    }

    /**
     * Processes this sniff when a registered token is encountered
     *
     * @param File    $phpcsFile The file being scanned
     * @param integer $stackPtr  The token position
     */
    public function process(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $this->getArrayCloser($phpcsFile, $tokens, $stackPtr);

        if ($closer === null || $tokens[$stackPtr]['line'] === $tokens[$closer]['line']) {
            return;
        }

        $arrows = [];
        $keyEnds = [];
        $arrowLines = [];

        for ($pointer = $stackPtr + 1; $pointer < $closer; ++$pointer) {
            $code = $tokens[$pointer]['code'];

            if ($code === T_OPEN_SHORT_ARRAY && isset($tokens[$pointer]['bracket_closer'])) {
                $pointer = $tokens[$pointer]['bracket_closer'];

                continue;
            }

            if ($code === T_ARRAY) {
                $nestedOpener = $tokens[$pointer]['parenthesis_opener'] ?? null;
                if ($nestedOpener !== null && isset($tokens[$nestedOpener]['parenthesis_closer'])) {
                    $pointer = $tokens[$nestedOpener]['parenthesis_closer'];

                    continue;
                }
            }

            if ($code !== T_DOUBLE_ARROW) {
                continue;
            }

            $previousToken = $phpcsFile->findPrevious(Tokens::$emptyTokens, $pointer - 1, null, true);
            if ($previousToken === false) {
                continue;
            }

            if ($previousToken <= $stackPtr) {
                continue;
            }

            $keyEnd = $tokens[$previousToken]['column'] + strlen((string) $tokens[$previousToken]['content']);
            $arrows[] = ['arrowPtr' => $pointer, 'keyEnd' => $keyEnd];
            $keyEnds[] = $keyEnd;
            $arrowLines[] = $tokens[$pointer]['line'];
        }

        if ($arrows === [] || count(array_unique($arrowLines)) === 1) {
            return;
        }

        $targetColumn = max($keyEnds) + 1;

        foreach ($arrows as $arrow) {
            $arrowColumn = $tokens[$arrow['arrowPtr']]['column'];
            if ($arrowColumn === $targetColumn) {
                continue;
            }

            $fix = $phpcsFile->addFixableError(
                sprintf(
                    'Array => arrow must be aligned to column %d; found at column %d',
                    $targetColumn,
                    $arrowColumn,
                ),
                $arrow['arrowPtr'],
                'ArrowNotAligned',
            );

            if ($fix) {
                $this->fixArrowAlignment($phpcsFile, $tokens, $arrow, $targetColumn);
            }
        }
    }

    /**
     * Returns the closer token for the array at the given position
     *
     * @param File                             $phpcsFile The file being scanned
     * @param array<int, array<string, mixed>> $tokens    The token stack
     * @param integer                          $stackPtr   The array token position
     */
    private function getArrayCloser(File $phpcsFile, array $tokens, int $stackPtr): ?int
    {
        if ($tokens[$stackPtr]['code'] === T_OPEN_SHORT_ARRAY) {
            return $tokens[$stackPtr]['bracket_closer'] ?? null;
        }

        if (isset($tokens[$stackPtr]['parenthesis_opener'])) {
            $opener = $tokens[$stackPtr]['parenthesis_opener'];

            return $tokens[$opener]['parenthesis_closer'] ?? null;
        }

        $opener = $phpcsFile->findNext(T_OPEN_PARENTHESIS, $stackPtr + 1);

        return $opener !== false ? ($tokens[$opener]['parenthesis_closer'] ?? null) : null;
    }

    /**
     * Fixes a misaligned arrow by padding the whitespace before it
     *
     * @param File                                 $phpcsFile    The file being scanned
     * @param array<int, array<string, mixed>>     $tokens       The token stack
     * @param array{arrowPtr: int, keyEnd: int}    $arrow        The arrow metadata
     * @param integer                              $targetColumn The target column
     */
    private function fixArrowAlignment(File $phpcsFile, array $tokens, array $arrow, int $targetColumn): void
    {
        $spacesNeeded = $targetColumn - $arrow['keyEnd'];
        if ($spacesNeeded <= 0) {
            return;
        }

        $padding = str_repeat(' ', $spacesNeeded);
        $beforePointer = $arrow['arrowPtr'] - 1;

        if ($tokens[$beforePointer]['code'] === T_WHITESPACE) {
            $phpcsFile->fixer->replaceToken($beforePointer, $padding);

            return;
        }

        $phpcsFile->fixer->replaceToken(
            $arrow['arrowPtr'],
            $padding.$tokens[$arrow['arrowPtr']]['content'],
        );
    }
}
