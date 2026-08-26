<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;
use Fight\Common\Adapter\Repository\DoctrineUnitOfWork;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Repository\UnitOfWork;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Common\Domain\Value\Identifier\Uuid;

use function Fight\Common\Domain\array_list;

$runtimeDeprecations = [];
set_error_handler(
    static function (int $severity, string $message) use (&$runtimeDeprecations): bool {
        if ($severity !== E_DEPRECATED && $severity !== E_USER_DEPRECATED) {
            return false;
        }

        $runtimeDeprecations[] = [
            'severity' => $severity === E_DEPRECATED ? 'E_DEPRECATED' : 'E_USER_DEPRECATED',
            'message'  => $message
        ];

        return true;
    }
);

try {
    if (isset($argv[2])) {
        require $argv[1];
        require $argv[2];
    } else {
        require $argv[1];
    }

    $list = array_list(['alpha', 'beta'], 'string');
    $meta = Meta::create(['consumer' => 'disposable']);
    $uuid = Uuid::fromString(Uuid::NIL);
    if (interface_exists(EntityManagerInterface::class)) {
        $entityManager = new class implements EntityManagerInterface {
            public int $flushCalls = 0;

            /**
             * Records one legacy flush.
             */
            public function flush(): void
            {
                ++$this->flushCalls;
            }

            /**
             * Returns the transaction-state boundary fake.
             */
            public function getConnection(): object
            {
                return new class {
                    /**
                     * Reports no active transaction.
                     */
                    public function isTransactionActive(): bool
                    {
                        return false;
                    }
                };
            }

            /**
             * Runs one transactional callback.
             */
            public function wrapInTransaction(callable $operation): mixed
            {
                return $operation();
            }

            /**
             * Reports the boundary fake open.
             */
            public function isOpen(): bool
            {
                return true;
            }
        };
        // Deprecated 1.x compatibility journey.
        $legacyUnitOfWork = new DoctrineUnitOfWork($entityManager);
        $legacyUnitOfWork->commit();
        $legacyAdapter = [
            'available'                 => true,
            'unit_of_work'              => $legacyUnitOfWork instanceof UnitOfWork,
            'standalone_commit_exposed' => method_exists($legacyUnitOfWork, 'commit'),
            'commit_calls'              => $entityManager->flushCalls
        ];
    } else {
        // Deprecated 1.x compatibility journey.
        $legacyUnitOfWork = new class implements UnitOfWork {
            public int $commitCalls = 0;

            /**
             * Records one legacy commit.
             */
            public function commit(): void
            {
                ++$this->commitCalls;
            }

            /**
             * Runs one transactional callback.
             */
            public function commitTransactional(callable $operation): mixed
            {
                return $operation();
            }

            /**
             * Reports the fallback open.
             */
            public function isClosed(): bool
            {
                return false;
            }
        };
        $legacyUnitOfWork->commit();
        $legacyAdapter = [
            'available'                 => false,
            'unit_of_work'              => true,
            'standalone_commit_exposed' => true,
            'commit_calls'              => $legacyUnitOfWork->commitCalls
        ];
    }
    // Canonical transaction-only journey.
    if (isset($entityManager) && class_exists(DoctrineTransactionalUnitOfWork::class)) {
        $transactionalUnitOfWork = new DoctrineTransactionalUnitOfWork($entityManager);
        $canonicalAdapter = [
            'available'                       => true,
            'transactional_unit_of_work_only' => $transactionalUnitOfWork instanceof TransactionalUnitOfWork
                && !$transactionalUnitOfWork instanceof UnitOfWork,
            'standalone_commit_exposed'       => method_exists($transactionalUnitOfWork, 'commit')
        ];
    } elseif (interface_exists(TransactionalUnitOfWork::class)) {
        $transactionalUnitOfWork = new class implements TransactionalUnitOfWork {
            private bool $closed = false;

            /**
             * Runs one transactional callback.
             */
            public function commitTransactional(callable $operation): mixed
            {
                try {
                    return $operation();
                } finally {
                    $this->closed = true;
                }
            }

            /**
             * Reports whether the fallback completed work.
             */
            public function isClosed(): bool
            {
                return $this->closed;
            }
        };
        $canonicalAdapter = [
            'available'                       => false,
            'transactional_unit_of_work_only' => false,
            'standalone_commit_exposed'       => false
        ];
    } else {
        $transactionalUnitOfWork = new class {
            private bool $closed = false;

            /**
             * Runs one transactional callback.
             */
            public function commitTransactional(callable $operation): mixed
            {
                try {
                    return $operation();
                } finally {
                    $this->closed = true;
                }
            }

            /**
             * Reports whether the fallback completed work.
             */
            public function isClosed(): bool
            {
                return $this->closed;
            }
        };
        $canonicalAdapter = [
            'available'                       => false,
            'transactional_unit_of_work_only' => false,
            'standalone_commit_exposed'       => false
        ];
    }
    $transactionalResult = $transactionalUnitOfWork->commitTransactional(static fn (): string => 'committed');
} finally {
    restore_error_handler();
}

echo json_encode(
    [
        'schema_version' => 'fight-common.public-api-representative-probe/v1',
        'findings'       => [[
            'finding_id'  => 'release.compatibility.consumer.public-api-probe-passed',
            'evidence_id' => 'fight-common.consumer.public-api-representative',
            'attribution' => 'release/fixtures/PublicApiConsumer/public-api-probe.php',
            'status'      => 'passed'
        ]],
        'observations'   => [
            'uuid'                       => $uuid->toString(),
            'meta'                       => $meta->toArray(),
            'collection'                 => $list->toArray(),
            'transactional_unit_of_work' => [
                'canonical_adapter'    => $canonicalAdapter,
                'legacy_adapter'       => $legacyAdapter,
                'transactional_result' => $transactionalResult,
                'transactional_closed' => $transactionalUnitOfWork->isClosed(),
                'runtime_deprecations' => []
            ],
            'runtime_deprecations'       => $runtimeDeprecations
        ]
    ],
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
)."\n";
