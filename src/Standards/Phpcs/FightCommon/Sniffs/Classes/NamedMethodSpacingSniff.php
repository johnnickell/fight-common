<?php

declare(strict_types=1);

namespace Fight\Common\Standards\Phpcs\FightCommon\Sniffs\Classes;

use Override;
use PHP_CodeSniffer\Files\File;
use SlevomatCodingStandard\Helpers\ClassHelper;
use SlevomatCodingStandard\Sniffs\Classes\MethodSpacingSniff;

/**
 * Class NamedMethodSpacingSniff
 *
 * Restricts Slevomat's method-spacing implementation to methods on named production types.
 */
final class NamedMethodSpacingSniff extends MethodSpacingSniff
{
    /**
     * Enforces method spacing outside tests and anonymous classes
     */
    #[Override]
    public function process(File $phpcsFile, int $methodPointer): void
    {
        $classPointer = ClassHelper::getClassPointer($phpcsFile, $methodPointer);

        if (
            $classPointer !== null
            && (
                $phpcsFile->getTokens()[$classPointer]['code'] === T_ANON_CLASS
                || str_ends_with(ClassHelper::getName($phpcsFile, $classPointer), 'Test')
            )
        ) {
            return;
        }

        parent::process($phpcsFile, $methodPointer);
    }
}
