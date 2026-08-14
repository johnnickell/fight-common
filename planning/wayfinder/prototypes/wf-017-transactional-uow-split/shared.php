<?php

declare(strict_types=1);

use Prototype\Contract\TransactionalUnitOfWork;
use Prototype\Contract\UnitOfWork;

/**
 * PROTOTYPE — the shared Application function knows only the narrower port.
 *
 * @param callable(): void $mutation
 */
function runPortableMutation(TransactionalUnitOfWork $unitOfWork, callable $mutation): mixed
{
    return $unitOfWork->commitTransactional(
        static function () use ($mutation): string {
            $mutation();

            return 'portable-result';
        },
    );
}

/** PROTOTYPE — an unchanged legacy consumer can still flush Doctrine pending state. */
function runLegacyCommit(UnitOfWork $unitOfWork): void
{
    $unitOfWork->commit();
}

/**
 * @param array<string, string|null> $versions
 * @param callable(): array{sessions: int, audits: int} $counts
 * @param null|callable(UnitOfWork): bool $legacyCommitProof
 * @return array<string, mixed>
 */
function runContractSplitProbe(
    string $lane,
    array $versions,
    TransactionalUnitOfWork $unitOfWork,
    callable $writePair,
    callable $counts,
    bool $expectsLegacyContract,
    ?callable $legacyCommitProof = null,
): array {
    prototypeAssert(
        is_subclass_of(UnitOfWork::class, TransactionalUnitOfWork::class),
        'legacy UnitOfWork must extend TransactionalUnitOfWork',
    );
    prototypeAssert($unitOfWork instanceof TransactionalUnitOfWork, 'adapter must satisfy the portable port');
    prototypeAssert(
        ($unitOfWork instanceof UnitOfWork) === $expectsLegacyContract,
        'only the legacy adapter may satisfy UnitOfWork',
    );
    prototypeAssert(
        method_exists($unitOfWork, 'commit') === $expectsLegacyContract,
        'record adapters must not expose commit()',
    );

    $initial = $counts();
    prototypeAssert($initial === ['sessions' => 0, 'audits' => 0], 'lane must start empty');

    $result = runPortableMutation(
        $unitOfWork,
        static fn (): mixed => $writePair('session-portable', 'audit-portable'),
    );
    $afterPortableMutation = $counts();
    prototypeAssert($result === 'portable-result', 'narrow port must preserve the callback result');
    prototypeAssert(
        $afterPortableMutation === ['sessions' => 1, 'audits' => 1],
        'portable mutation and audit must commit together',
    );

    $legacyCommitPreserved = null;
    if ($legacyCommitProof !== null) {
        prototypeAssert($unitOfWork instanceof UnitOfWork, 'legacy proof requires legacy UnitOfWork');
        $legacyCommitPreserved = $legacyCommitProof($unitOfWork);
        prototypeAssert($legacyCommitPreserved, 'legacy Doctrine commit must flush pending state');
    }

    prototypeAssert(!$unitOfWork->isClosed(), 'successful unit of work must remain open');

    return [
        'prototype' => 'WF-017 TransactionalUnitOfWork split',
        'lane' => $lane,
        'versions' => $versions,
        'state' => [
            'initial' => $initial,
            'after_portable_mutation' => $afterPortableMutation,
            'after_legacy_commit' => $counts(),
        ],
        'observations' => [
            'legacy_unit_of_work_extends_transactional' => true,
            'portable_application_accepts_adapter' => true,
            'adapter_implements_transactional_unit_of_work' => true,
            'adapter_implements_legacy_unit_of_work' => $unitOfWork instanceof UnitOfWork,
            'adapter_exposes_commit' => method_exists($unitOfWork, 'commit'),
            'callback_return_preserved' => true,
            'atomic_session_and_audit_commit' => true,
            'legacy_doctrine_commit_preserved' => $legacyCommitPreserved,
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
function writeReceipt(array $receipt): void
{
    $json = json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    $receiptPath = getenv('PROTOTYPE_RECEIPT');
    if (is_string($receiptPath) && $receiptPath !== '') {
        file_put_contents($receiptPath, $json);
    }

    echo $json;
}
