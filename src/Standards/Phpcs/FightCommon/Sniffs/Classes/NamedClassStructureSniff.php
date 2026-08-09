<?php

declare(strict_types=1);

namespace Fight\Common\Standards\Phpcs\FightCommon\Sniffs\Classes;

use Override;
use PHP_CodeSniffer\Files\File;
use SlevomatCodingStandard\Helpers\ClassHelper;
use SlevomatCodingStandard\Helpers\TokenHelper;
use SlevomatCodingStandard\Sniffs\Classes\ClassStructureSniff;

/**
 * Restricts Slevomat's class-structure implementation to named production types.
 */
final class NamedClassStructureSniff extends ClassStructureSniff
{
    /** @return list<int> */
    #[Override]
    public function register(): array
    {
        return TokenHelper::CLASS_TYPE_TOKEN_CODES;
    }

    /**
     * Enforces class structure on named production types
     */
    #[Override]
    public function process(File $phpcsFile, int $stackPtr): int
    {
        if (str_ends_with(ClassHelper::getName($phpcsFile, $stackPtr), 'Test')) {
            return $stackPtr + 1;
        }

        return parent::process($phpcsFile, $stackPtr);
    }
}
