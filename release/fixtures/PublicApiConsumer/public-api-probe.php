<?php

declare(strict_types=1);

use Fight\Common\Domain\Messaging\Meta;
use Fight\Common\Domain\Value\Identifier\Uuid;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Application\Repository\UnitOfWork;

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
    require $argv[1];

    $list = array_list(['alpha', 'beta'], 'string');
    $meta = Meta::create(['consumer' => 'disposable']);
    $uuid = Uuid::fromString(Uuid::NIL);
    $legacyUnitOfWork = new class implements UnitOfWork {
        public int $commitCalls = 0;

        public function commit(): void
        {
            ++$this->commitCalls;
        }

        public function commitTransactional(callable $operation): mixed
        {
            return $operation();
        }

        public function isClosed(): bool
        {
            return false;
        }
    };
    if (interface_exists(TransactionalUnitOfWork::class)) {
        $transactionalUnitOfWork = new class implements TransactionalUnitOfWork {
            private bool $closed = false;

            public function commitTransactional(callable $operation): mixed
            {
                try {
                    return $operation();
                } finally {
                    $this->closed = true;
                }
            }

            public function isClosed(): bool
            {
                return $this->closed;
            }
        };
    } else {
        $transactionalUnitOfWork = new class {
            private bool $closed = false;

            public function commitTransactional(callable $operation): mixed
            {
                try {
                    return $operation();
                } finally {
                    $this->closed = true;
                }
            }

            public function isClosed(): bool
            {
                return $this->closed;
            }
        };
    }
    $legacyUnitOfWork->commit();
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
            'uuid'                 => $uuid->toString(),
            'meta'                 => $meta->toArray(),
            'collection'           => $list->toArray(),
            'transactional_unit_of_work' => [
                'legacy_commit_calls' => $legacyUnitOfWork->commitCalls,
                'transactional_result' => $transactionalResult,
                'transactional_closed' => $transactionalUnitOfWork->isClosed(),
                'runtime_deprecations' => []
            ],
            'runtime_deprecations' => $runtimeDeprecations
        ]
    ],
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
)."\n";
