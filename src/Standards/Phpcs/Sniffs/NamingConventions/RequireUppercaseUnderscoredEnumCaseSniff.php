<?php

declare(strict_types=1);

namespace Fight\Common\Standards\Phpcs\Sniffs\NamingConventions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Class RequireUppercaseUnderscoredEnumCaseSniff
 */
final class RequireUppercaseUnderscoredEnumCaseSniff implements Sniff
{
    /**
     * Registers the tokens that this sniff wants to listen for
     *
     * @return list<int|string>
     */
    public function register(): array
    {
        return [T_ENUM_CASE];
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
        $namePtr = $phpcsFile->findNext(T_STRING, $stackPtr + 1);

        if ($namePtr === false) {
            return;
        }

        $name = $tokens[$namePtr]['content'];
        if (preg_match('/\A[A-Z][A-Z0-9]*(?:_[A-Z0-9]+)*\z/', $name) === 1) {
            return;
        }

        $phpcsFile->addError(
            'Enum case "%s" must use UPPERCASE_UNDERSCORED formatting',
            $namePtr,
            'NotUppercaseUnderscored',
            [$name]
        );
    }
}
