<?php

declare(strict_types=1);

namespace Fight\Common\Standards\Phpcs\FightCommon\Sniffs\Classes;

use Override;
use PHP_CodeSniffer\Files\File;
use SlevomatCodingStandard\Helpers\ClassHelper;
use SlevomatCodingStandard\Helpers\TokenHelper;
use SlevomatCodingStandard\Sniffs\Classes\ClassMemberSpacingSniff;

/**
 * Restricts Slevomat's member-spacing implementation to named production types.
 */
final class NamedClassMemberSpacingSniff extends ClassMemberSpacingSniff
{
    /** @return list<int> */
    #[Override]
    public function register(): array
    {
        return TokenHelper::CLASS_TYPE_TOKEN_CODES;
    }

    /**
     * Enforces member spacing on named production types
     */
    #[Override]
    public function process(File $phpcsFile, int $stackPtr): void
    {
        if (str_ends_with(ClassHelper::getName($phpcsFile, $stackPtr), 'Test')) {
            return;
        }

        parent::process($phpcsFile, $stackPtr);
    }
}
