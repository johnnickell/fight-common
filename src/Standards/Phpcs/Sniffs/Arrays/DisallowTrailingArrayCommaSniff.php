<?php

declare(strict_types=1);

namespace Fight\Common\Standards\Phpcs\Sniffs\Arrays;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Class DisallowTrailingArrayCommaSniff
 */
final class DisallowTrailingArrayCommaSniff implements Sniff
{
    /**
     * Registers the tokens that this sniff wants to listen for
     *
     * @return list<int|string>
     */
    public function register(): array
    {
        return [T_COMMA];
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
        $nextNonEmpty = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);

        if ($nextNonEmpty === false) {
            return;
        }

        if ($tokens[$nextNonEmpty]['code'] === T_CLOSE_SHORT_ARRAY) {
            $this->reportTrailingComma($phpcsFile, $stackPtr);

            return;
        }

        if ($tokens[$nextNonEmpty]['code'] !== T_CLOSE_PARENTHESIS) {
            return;
        }

        $opener = $tokens[$nextNonEmpty]['parenthesis_opener'] ?? null;
        if ($opener === null) {
            return;
        }

        $beforeOpen = $phpcsFile->findPrevious(Tokens::$emptyTokens, $opener - 1, null, true);
        if ($beforeOpen !== false && $tokens[$beforeOpen]['code'] === T_ARRAY) {
            $this->reportTrailingComma($phpcsFile, $stackPtr);
        }
    }

    /**
     * Reports and optionally fixes a trailing comma violation
     *
     * @param File    $phpcsFile The file being scanned
     * @param integer $stackPtr  The trailing comma position
     */
    private function reportTrailingComma(File $phpcsFile, int $stackPtr): void
    {
        $fix = $phpcsFile->addFixableError(
            'Trailing comma found in array declaration',
            $stackPtr,
            'DisallowTrailingArrayComma'
        );

        if ($fix) {
            $phpcsFile->fixer->replaceToken($stackPtr, '');
        }
    }
}
