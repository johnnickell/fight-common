<?php

declare(strict_types=1);

use Fight\Common\Application\Repository\UnitOfWork;

/**
 * PROTOTYPE — throwaway executable evidence for WF-017.
 *
 * @param callable(string, string): void $writePair
 * @param callable(): array{sessions: int, audits: int} $counts
 * @param callable(): UnitOfWork $freshUnitOfWork
 * @return array<string, mixed>
 */
function runTransactionProbe(
    string $lane,
    array $versions,
    UnitOfWork $unitOfWork,
    callable $writePair,
    callable $counts,
    callable $freshUnitOfWork,
    string $commitSemantics,
): array {
    $initial = $counts();
    prototypeAssert($initial === ['sessions' => 0, 'audits' => 0], 'lane must start empty');

    $returned = $unitOfWork->commitTransactional(
        static function () use ($writePair): string {
            $writePair('session-committed', 'audit-committed');

            return 'session-committed';
        },
    );
    $afterCommit = $counts();
    prototypeAssert($returned === 'session-committed', 'transaction must preserve callback return value');
    prototypeAssert($afterCommit === ['sessions' => 1, 'audits' => 1], 'session and audit must commit together');

    $rollbackException = null;
    try {
        $unitOfWork->commitTransactional(
            static function () use ($writePair): never {
                $writePair('session-rolled-back', 'audit-rolled-back');
                throw new RuntimeException('PROTOTYPE forced rollback');
            },
        );
    } catch (Throwable $throwable) {
        $rollbackException = $throwable::class;
    }

    $afterRollback = $counts();
    prototypeAssert($rollbackException === RuntimeException::class, 'transaction must propagate the application failure');
    prototypeAssert($afterRollback === $afterCommit, 'rollback must leave neither session nor audit residue');

    $nestedException = null;
    $fresh = $freshUnitOfWork();
    try {
        $fresh->commitTransactional(
            static fn (): mixed => $fresh->commitTransactional(static fn (): string => 'nested'),
        );
    } catch (Throwable $throwable) {
        $nestedException = $throwable::class;
    }

    return [
        'prototype' => 'WF-017 transaction seam',
        'lane' => $lane,
        'versions' => $versions,
        'state' => [
            'initial' => $initial,
            'after_commit' => $afterCommit,
            'after_forced_rollback' => $afterRollback,
        ],
        'observations' => [
            'callback_return_preserved' => true,
            'atomic_session_and_audit_commit' => true,
            'atomic_session_and_audit_rollback' => true,
            'rollback_exception_propagated' => true,
            'closed_after_rollback' => $unitOfWork->isClosed(),
            'nested_transaction_rejected' => $nestedException !== null,
            'nested_transaction_exception' => $nestedException,
            'commit_semantics' => $commitSemantics,
        ],
    ];
}

function prototypeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param array<string, mixed> $receipt */
function printReceipt(array $receipt): never
{
    $json = json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    $receiptPath = getenv('PROTOTYPE_RECEIPT');
    if (is_string($receiptPath) && $receiptPath !== '') {
        file_put_contents($receiptPath, $json);
    }

    echo $json;
    exit(0);
}
