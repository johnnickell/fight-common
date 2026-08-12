<?php

declare(strict_types=1);

namespace Fight\Common\Standards\Phpcs\Sniffs\Files;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Class RequireStrictTypesSniff
 */
final class RequireStrictTypesSniff implements Sniff
{
    /**
     * Registers the tokens that this sniff wants to listen for
     *
     * @return list<int|string>
     */
    public function register(): array
    {
        return [T_OPEN_TAG];
    }

    /**
     * Processes this sniff when a registered token is encountered
     *
     * @param File    $phpcsFile The file being scanned
     * @param integer $stackPtr  The token position
     */
    public function process(File $phpcsFile, int $stackPtr): void
    {
        if ($phpcsFile->findPrevious(T_OPEN_TAG, $stackPtr - 1) !== false) {
            return;
        }

        if ($this->hasStrictTypesDeclaration($phpcsFile)) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Missing declare(strict_types=1) at the top of the file',
            $stackPtr,
            'Missing',
        );

        if ($fix) {
            $phpcsFile->fixer->addContent($stackPtr, "\n\ndeclare(strict_types=1);");
        }
    }

    /**
     * Determines whether the file contains declare(strict_types=1)
     *
     * @param File $phpcsFile The file being scanned
     */
    private function hasStrictTypesDeclaration(File $phpcsFile): bool
    {
        $tokens = $phpcsFile->getTokens();
        $declarePtr = $phpcsFile->findNext(T_DECLARE, 0);

        while ($declarePtr !== false) {
            $openParen = $phpcsFile->findNext(T_OPEN_PARENTHESIS, $declarePtr + 1);

            if ($openParen !== false && isset($tokens[$openParen]['parenthesis_closer'])) {
                $closeParen = $tokens[$openParen]['parenthesis_closer'];
                $directive = $phpcsFile->findNext(T_STRING, $openParen + 1, $closeParen);

                if ($directive !== false && strtolower((string) $tokens[$directive]['content']) === 'strict_types') {
                    $value = $phpcsFile->findNext(T_LNUMBER, $directive + 1, $closeParen);

                    if ($value !== false && $tokens[$value]['content'] === '1') {
                        return true;
                    }
                }
            }

            $declarePtr = $phpcsFile->findNext(T_DECLARE, $declarePtr + 1);
        }

        return false;
    }
}
