<?php

declare(strict_types=1);

namespace Fight\Common\Standards\Phpcs\Sniffs\Formatting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use SlevomatCodingStandard\Helpers\ClassHelper;
use SlevomatCodingStandard\Helpers\CommentHelper;
use SlevomatCodingStandard\Helpers\FixerHelper;
use SlevomatCodingStandard\Helpers\PropertyHelper;
use SlevomatCodingStandard\Helpers\TokenHelper;

/**
 * Class RequireVisibilityGroupSpacingSniff
 *
 * Enforces visibility-sensitive constant and property spacing on named production types.
 */
final class RequireVisibilityGroupSpacingSniff implements Sniff
{
    private const string KIND_CONSTANT = 'constant';
    private const string KIND_PROPERTY = 'property';

    /**
     * Registers named and anonymous type tokens for spacing enforcement
     *
     * @return list<int|string>
     */
    public function register(): array
    {
        return TokenHelper::CLASS_TYPE_WITH_ANONYMOUS_CLASS_TOKEN_CODES;
    }

    /**
     * Enforces visibility-sensitive constant and property spacing
     */
    public function process(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if (
            $tokens[$stackPtr]['code'] === T_ANON_CLASS
            || str_ends_with(ClassHelper::getName($phpcsFile, $stackPtr), 'Test')
        ) {
            return;
        }

        $members = $this->members($phpcsFile, $stackPtr);

        for ($index = 1; $index < count($members); $index++) {
            $previous = $members[$index - 1];
            $current = $members[$index];

            if ($previous['kind'] === null) {
                continue;
            }

            if ($previous['kind'] !== $current['kind']) {
                continue;
            }

            $this->enforceGap($phpcsFile, $previous, $current);
        }
    }

    /**
     * Collects top-level members and their spacing groups
     *
     * @return list<array{pointer: int, kind: string|null, visibility: string|null}>
     */
    private function members(File $phpcsFile, int $stackPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $scopeLevel = $tokens[$stackPtr]['level'] + 1;
        $cursor = $tokens[$stackPtr]['scope_opener'];
        $members = [];

        while (true) {
            $cursor = TokenHelper::findNext(
                $phpcsFile,
                [T_USE, T_ENUM_CASE, T_CONST, T_VARIABLE, T_FUNCTION],
                $cursor + 1,
                $tokens[$stackPtr]['scope_closer'],
            );

            if ($cursor === null) {
                return $members;
            }

            if ($tokens[$cursor]['level'] !== $scopeLevel) {
                continue;
            }

            if ($tokens[$cursor]['code'] === T_VARIABLE && !PropertyHelper::isProperty($phpcsFile, $cursor)) {
                continue;
            }

            $kind = match ($tokens[$cursor]['code']) {
                T_CONST => self::KIND_CONSTANT,
                T_VARIABLE => self::KIND_PROPERTY,
                default => null,
            };
            $visibility = match ($kind) {
                self::KIND_CONSTANT => $this->constantVisibility($phpcsFile, $cursor),
                self::KIND_PROPERTY => (string) $phpcsFile->getMemberProperties($cursor)['scope'],
                default => null,
            };
            $members[] = ['pointer' => $cursor, 'kind' => $kind, 'visibility' => $visibility];

            if ($tokens[$cursor]['code'] === T_VARIABLE) {
                $cursor = PropertyHelper::getEndPointer($phpcsFile, $cursor);
            } elseif ($tokens[$cursor]['code'] === T_FUNCTION) {
                if (array_key_exists('scope_closer', $tokens[$cursor])) {
                    $cursor = $tokens[$cursor]['scope_closer'];
                } else {
                    $cursor = (int) TokenHelper::findNext($phpcsFile, T_SEMICOLON, $cursor + 1);
                }
            } elseif ($tokens[$cursor]['code'] === T_USE && array_key_exists('scope_closer', $tokens[$cursor])) {
                $cursor = $tokens[$cursor]['scope_closer'];
            }
        }
    }

    /**
     * Retrieves a constant's declared or implicit visibility
     */
    private function constantVisibility(File $phpcsFile, int $pointer): string
    {
        $tokens = $phpcsFile->getTokens();
        $cursor = $pointer;

        while (true) {
            $cursor = TokenHelper::findPrevious(
                $phpcsFile,
                [T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET, T_SEMICOLON, T_PUBLIC, T_PROTECTED, T_PRIVATE],
                $cursor - 1,
            );

            if (in_array($tokens[$cursor]['code'], [T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET, T_SEMICOLON], true)) {
                return 'public';
            }

            return match ($tokens[$cursor]['code']) {
                T_PROTECTED => 'protected',
                T_PRIVATE => 'private',
                default => 'public',
            };
        }
    }

    /**
     * Enforces the gap between two adjacent members of one kind
     *
     * @param File                                                          $phpcsFile Scanned file
     * @param array{pointer: int, kind: string|null, visibility: string|null} $previous
     * @param array{pointer: int, kind: string|null, visibility: string|null} $current
     */
    private function enforceGap(File $phpcsFile, array $previous, array $current): void
    {
        $previousEnd = $this->memberEnd($phpcsFile, $previous['pointer']);
        $currentStart = $this->memberStart($phpcsFile, $current['pointer']);
        $tokens = $phpcsFile->getTokens();
        $actual = $tokens[$currentStart]['line'] - $tokens[$previousEnd]['line'] - 1;
        $expected = $previous['visibility'] === $current['visibility'] ? 0 : 1;

        if ($actual === $expected) {
            return;
        }

        if ($expected === 0) {
            $message = sprintf(
                'Expected no blank line within the %s %s group; found %d',
                $current['visibility'],
                $current['kind'],
                $actual,
            );
            $code = 'UnexpectedBlankLineWithinVisibilityGroup';
        } else {
            $message = sprintf(
                'Expected one blank line between %s and %s %s groups; found %d',
                $previous['visibility'],
                $current['visibility'],
                $current['kind'],
                $actual,
            );
            $code = 'MissingBlankLineBetweenVisibilityGroups';
        }

        $firstPointerOnLine = TokenHelper::findFirstTokenOnLine($phpcsFile, $currentStart);

        if (TokenHelper::findNextNonWhitespace($phpcsFile, $previousEnd + 1, $firstPointerOnLine) !== null) {
            $phpcsFile->addError($message, $current['pointer'], $code);

            return;
        }

        if (!$phpcsFile->addFixableError($message, $current['pointer'], $code)) {
            return;
        }

        $phpcsFile->fixer->beginChangeset();
        FixerHelper::add($phpcsFile, $previousEnd, str_repeat($phpcsFile->eolChar, $expected + 1));
        FixerHelper::removeBetween($phpcsFile, $previousEnd, $firstPointerOnLine);
        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Retrieves the first token belonging to a member declaration
     */
    private function memberStart(File $phpcsFile, int $memberPointer): int
    {
        $tokens = $phpcsFile->getTokens();
        $firstCode = $this->memberFirstCode($phpcsFile, $memberPointer);

        while (true) {
            $pointerBefore = TokenHelper::findPreviousNonWhitespace($phpcsFile, $firstCode - 1);

            if ($tokens[$pointerBefore]['code'] === T_ATTRIBUTE_END) {
                $firstCode = $tokens[$pointerBefore]['attribute_opener'];
                continue;
            }

            if (in_array($tokens[$pointerBefore]['code'], Tokens::COMMENT_TOKENS, true)) {
                $pointerBeforeComment = TokenHelper::findPreviousEffective($phpcsFile, $pointerBefore - 1);

                if ($tokens[$pointerBeforeComment]['line'] !== $tokens[$pointerBefore]['line']) {
                    if (array_key_exists('comment_opener', $tokens[$pointerBefore])) {
                        $firstCode = $tokens[$pointerBefore]['comment_opener'];
                    } else {
                        $firstCode = CommentHelper::getMultilineCommentStartPointer($phpcsFile, $pointerBefore);
                    }

                    continue;
                }
            }

            return $firstCode;
        }
    }

    /**
     * Retrieves the first code token in a member declaration
     */
    private function memberFirstCode(File $phpcsFile, int $memberPointer): int
    {
        $tokens = $phpcsFile->getTokens();
        $endCodes = [T_SEMICOLON, T_CLOSE_CURLY_BRACKET, T_OPEN_CURLY_BRACKET];
        $searchCodes = [...TokenHelper::MODIFIERS_TOKEN_CODES, ...$endCodes];
        $firstCode = $memberPointer;
        $previousFirstCode = $memberPointer;

        while (true) {
            $firstCode = TokenHelper::findPrevious($phpcsFile, $searchCodes, $firstCode - 1);

            if (in_array($tokens[$firstCode]['code'], $endCodes, true)) {
                return $previousFirstCode;
            }

            $previousFirstCode = $firstCode;
        }
    }

    /**
     * Retrieves the final token in a constant or property declaration
     */
    private function memberEnd(File $phpcsFile, int $memberPointer): int
    {
        if ($phpcsFile->getTokens()[$memberPointer]['code'] === T_VARIABLE) {
            return PropertyHelper::getEndPointer($phpcsFile, $memberPointer);
        }

        return (int) TokenHelper::findNext($phpcsFile, T_SEMICOLON, $memberPointer + 1);
    }
}
