<?php

declare(strict_types=1);

namespace Fight\Common\Standards\Phpcs\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Class RequireMethodDocCommentSniff
 */
final class RequireMethodDocCommentSniff implements Sniff
{
    private const array APPROVED_VERBS = [
        'Acquires', 'Adapts', 'Adds', 'Advances', 'Aggregates', 'Allows', 'Appends', 'Applies', 'Approves',
        'Asks', 'Asserts', 'Assigns', 'Attempts', 'Authenticates', 'Backfills', 'Bounds', 'Bridges', 'Builds',
        'Calculates', 'Carries', 'Changes', 'Checks', 'Classifies', 'Clears', 'Collects', 'Completes', 'Composes',
        'Computes', 'Conducts', 'Configures', 'Converges', 'Converts', 'Coordinates', 'Counts', 'Creates', 'Decides',
        'Declares', 'Decodes', 'Decrypts', 'Defines', 'Deletes', 'Derives', 'Describes', 'Detects', 'Determines',
        'Disables', 'Discovers', 'Disengages', 'Dispatches', 'Edits', 'Enables', 'Encodes', 'Encrypts', 'Ends',
        'Enforces', 'Engages', 'Ensures', 'Erases', 'Evaluates', 'Exchanges', 'Executes', 'Exports', 'Extracts',
        'Fails', 'Fetches', 'Finds', 'Fixes', 'Formats', 'Generates', 'Gets', 'Grants', 'Handles', 'Hides',
        'Identifies', 'Initializes', 'Injects', 'Inserts', 'Invalidates', 'Invokes', 'Issues', 'Iterates', 'Leaves',
        'Links', 'Lists', 'Loads', 'Logs', 'Maps', 'Marks', 'Masks', 'Matches', 'Normalizes', 'Opens', 'Parses',
        'Performs', 'Persists', 'Prevents', 'Processes', 'Projects', 'Provisions', 'Qualifies', 'Reads', 'Rebuilds',
        'Reconciles', 'Reconstitutes', 'Records', 'Redacts', 'Refreshes', 'Registers', 'Reindexes', 'Rejects',
        'Releases', 'Removes', 'Renames', 'Renders', 'Reorders', 'Replaces', 'Reports', 'Represents', 'Requests',
        'Requires', 'Resolves', 'Restores', 'Retrieves', 'Returns', 'Reveals', 'Revokes', 'Runs', 'Scans',
        'Schedules', 'Searches', 'Sends', 'Serializes', 'Sets', 'Sorts', 'Stages', 'Starts', 'Stores', 'Streams',
        'Strips', 'Summarizes', 'Synchronizes', 'Tests', 'Transitions', 'Translates', 'Unloads', 'Updates', 'Uses',
        'Validates', 'Verifies', 'Wraps', 'Writes'
    ];
    private const array IMPERATIVE_MAP = [
        'Add'       => 'Adds',
        'Build'     => 'Builds',
        'Check'     => 'Checks',
        'Compute'   => 'Computes',
        'Configure' => 'Configures',
        'Convert'   => 'Converts',
        'Create'    => 'Creates',
        'Execute'   => 'Executes',
        'Find'      => 'Finds',
        'Get'       => 'Gets',
        'Handle'    => 'Handles',
        'Process'   => 'Processes',
        'Remove'    => 'Removes',
        'Release'   => 'Releases',
        'Resolve'   => 'Resolves',
        'Restore'   => 'Restores',
        'Return'    => 'Returns',
        'Send'      => 'Sends',
        'Stream'    => 'Streams',
        'Summarize' => 'Summarizes',
        'Unload'    => 'Unloads',
        'Update'    => 'Updates'
    ];

    /**
     * Registers function tokens for method documentation enforcement
     *
     * @return list<int>
     */
    public function register(): array
    {
        return [T_FUNCTION];
    }

    /**
     * Enforces constructor and method documentation grammar
     */
    public function process(File $phpcsFile, int $stackPtr): void
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);
        $owner = $this->owningType($phpcsFile, $stackPtr);

        if ($name === '' || $owner === null || str_ends_with($owner['name'], 'Test')) {
            return;
        }

        $isConstructor = strtolower($name) === '__construct';
        $expected = 'Constructs '.$owner['name'];
        $modifiers = [T_ABSTRACT, T_FINAL, T_PRIVATE, T_PROTECTED, T_PUBLIC, T_STATIC];
        $comment = DocumentationComment::find($phpcsFile, $stackPtr, $modifiers);

        if ($comment === null) {
            $message = sprintf('Missing doc comment for method %s::%s', $owner['name'], $name);

            if ($isConstructor) {
                if ($phpcsFile->addFixableError($message, $stackPtr, 'MissingDocComment')) {
                    DocumentationComment::insert(
                        $phpcsFile,
                        DocumentationComment::declarationStart($phpcsFile, $stackPtr, $modifiers),
                        [$expected]
                    );
                }
            } else {
                $phpcsFile->addError($message, $stackPtr, 'MissingDocComment');
            }

            return;
        }

        $lines = $comment['lines'];
        $first = DocumentationComment::firstContentLine($lines);

        if ($first === null) {
            $phpcsFile->addError('Missing method summary', $stackPtr, 'AmbiguousSummary');

            return;
        }

        $lines = array_slice($lines, $first);
        $summary = $lines[0];

        if ($summary === '@inheritDoc') {
            if (count($lines) !== 1) {
                $phpcsFile->addError(
                    'Bare @inheritDoc must be the complete doc comment',
                    $stackPtr,
                    'InheritDocWithContent'
                );
            }

            return;
        }

        $fix = false;

        if (str_starts_with($summary, '@')) {
            if (!$isConstructor) {
                $phpcsFile->addError(
                    'Annotation-first methods require a human-written summary',
                    $stackPtr,
                    'AmbiguousSummary'
                );

                return;
            }

            $fix = $phpcsFile->addFixableError(
                sprintf('Expected "%s" before constructor annotations', $expected),
                $stackPtr,
                'InvalidConstructorSummary'
            );
            $lines = array_merge([$expected, ''], $lines);
        } elseif ($isConstructor) {
            if ($summary === $expected) {
                // The constructor summary is already canonical.
            } elseif (rtrim($summary, '.!?') === $expected) {
                $fix = $phpcsFile->addFixableError(
                    'Constructor summary must not end in punctuation',
                    $stackPtr,
                    'TerminalPunctuation'
                );
                $lines[0] = $expected;
            } else {
                $fix = $phpcsFile->addFixableError(
                    sprintf('Expected "%s" as the constructor summary', $expected),
                    $stackPtr,
                    'InvalidConstructorSummary'
                );
                $lines = array_merge([$expected, ''], $lines);
            }
        } else {
            $summaryFix = $this->validateMethodSummary($phpcsFile, $stackPtr, $lines);

            if ($summaryFix === null) {
                return;
            }

            $fix = $summaryFix;
        }

        $separated = DocumentationComment::normalizeSeparator($lines);

        if ($separated['changed']) {
            $fix = $phpcsFile->addFixableError(
                'Expected exactly one blank docblock line after the method summary',
                $stackPtr,
                'MissingBlankLine'
            ) || $fix;
            $lines = $separated['lines'];
        }

        if ($fix) {
            DocumentationComment::replace($phpcsFile, $comment, $lines);
        }
    }

    /**
     * Validates and deterministically normalizes a non-constructor summary
     *
     * @param File $phpcsFile The file being scanned
     * @param integer $stackPtr The method token pointer
     * @param array $lines Normalized docblock lines
     *
     * @phpstan-param list<string> $lines
     */
    private function validateMethodSummary(File $phpcsFile, int $stackPtr, array &$lines): ?bool
    {
        $summary = $lines[0];

        if (isset($lines[1]) && $lines[1] !== '' && !str_starts_with($lines[1], '@')) {
            $phpcsFile->addError('Method summary must occupy exactly one line', $stackPtr, 'WrappedSummary');

            return null;
        }

        preg_match('/^([A-Za-z]+)\b/', (string) $summary, $matches);
        $verb = $matches[1] ?? '';
        $fix = false;

        if (!in_array($verb, self::APPROVED_VERBS, true)) {
            if (!isset(self::IMPERATIVE_MAP[$verb])) {
                $phpcsFile->addError(
                    sprintf('Method summary must begin with an approved action verb; found "%s"', $verb),
                    $stackPtr,
                    'AmbiguousSummary'
                );

                return null;
            }

            $fix = $phpcsFile->addFixableError(
                sprintf('Use "%s" instead of imperative "%s"', self::IMPERATIVE_MAP[$verb], $verb),
                $stackPtr,
                'UnapprovedVerb'
            );
            $lines[0] = self::IMPERATIVE_MAP[$verb].substr((string) $summary, strlen($verb));
        }

        if (preg_match('/[.!?]$/', (string) $lines[0]) === 1) {
            $fix = $phpcsFile->addFixableError(
                'Method summary must not end in punctuation',
                $stackPtr,
                'TerminalPunctuation'
            ) || $fix;
            $lines[0] = substr((string) $lines[0], 0, -1);
        }

        return $fix;
    }

    /**
     * Finds the named type that owns a method
     *
     * @return array{pointer: int, name: string}|null
     */
    private function owningType(File $phpcsFile, int $stackPtr): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $conditions = array_reverse($tokens[$stackPtr]['conditions'] ?? [], true);

        foreach ($conditions as $pointer => $code) {
            if (in_array($code, [T_ANON_CLASS, T_CLOSURE, T_FN, T_FUNCTION], true)) {
                return null;
            }

            if (!in_array($code, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                continue;
            }

            $name = $phpcsFile->getDeclarationName((int) $pointer);

            return $name === '' ? null : ['pointer' => (int) $pointer, 'name' => $name];
        }

        return null;
    }
}
