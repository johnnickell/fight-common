<?php

declare(strict_types=1);

namespace Fight\Common\Standards\Phpcs\FightCommon\Sniffs\Formatting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Class RequireBlankLineBeforeReturnSniff
 */
final class RequireBlankLineBeforeReturnSniff implements Sniff
{
    /**
     * Registers the tokens that this sniff wants to listen for
     *
     * @return list<int|string>
     */
    public function register(): array
    {
        return [T_RETURN];
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
        $previousStatement = $phpcsFile->findPrevious(Tokens::$emptyTokens, $stackPtr - 1, null, true);

        if ($previousStatement === false) {
            return;
        }

        if (in_array($tokens[$previousStatement]['code'], [T_OPEN_CURLY_BRACKET, T_COLON, T_OPEN_TAG], true)) {
            return;
        }

        $blockTop = $this->attachedBlockTop($phpcsFile, $stackPtr);
        if ($tokens[$blockTop]['line'] - $tokens[$previousStatement]['line'] > 1) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Expected a blank line before the return statement',
            $stackPtr,
            'Missing',
        );

        if ($fix) {
            $phpcsFile->fixer->addNewlineBefore($blockTop);
        }
    }

    /**
     * Returns the topmost token of the attached comment block
     *
     * @param File    $phpcsFile The file being scanned
     * @param integer $stackPtr  The return token position
     */
    private function attachedBlockTop(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $top = $stackPtr;
        $candidate = $phpcsFile->findPrevious(T_WHITESPACE, $stackPtr - 1, null, true);

        while (
            $candidate !== false
            && in_array($tokens[$candidate]['code'], Tokens::$commentTokens, true)
            && $tokens[$candidate]['line'] === $tokens[$top]['line'] - 1
        ) {
            $top = $candidate;
            $candidate = $phpcsFile->findPrevious(T_WHITESPACE, $top - 1, null, true);
        }

        return $top;
    }
}
