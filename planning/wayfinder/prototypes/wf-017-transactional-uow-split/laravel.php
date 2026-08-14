<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\ConnectionInterface;
use Prototype\Contract\TransactionalUnitOfWork;

require __DIR__ . '/../wf-017-transaction-seam/laravel/vendor/autoload.php';
require __DIR__ . '/contracts.php';
require __DIR__ . '/shared.php';

final class CandidateLaravelUnitOfWork implements TransactionalUnitOfWork
{
    private bool $active = false;

    public function __construct(private readonly ConnectionInterface $connection) {}

    public function commitTransactional(callable $operation): mixed
    {
        if ($this->active) {
            throw new LogicException('Nested UnitOfWork transactions are not supported.');
        }

        $this->active = true;
        try {
            return $this->connection->transaction(static fn (): mixed => $operation());
        } finally {
            $this->active = false;
        }
    }

    public function isClosed(): bool
    {
        return false;
    }
}

$capsule = new Manager();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$connection = $capsule->getConnection();
$schema = $connection->getSchemaBuilder();
$schema->create('sessions', static fn ($table) => $table->string('id')->primary());
$schema->create('audits', static fn ($table) => $table->string('id')->primary());
$counts = static fn (): array => [
    'sessions' => $connection->table('sessions')->count(),
    'audits' => $connection->table('audits')->count(),
];

writeReceipt(runContractSplitProbe(
    'Laravel native transaction-only adapter',
    ['illuminate/database' => Composer\InstalledVersions::getPrettyVersion('illuminate/database')],
    new CandidateLaravelUnitOfWork($connection),
    static function (string $sessionId, string $auditId) use ($connection): void {
        $connection->table('sessions')->insert(['id' => $sessionId]);
        $connection->table('audits')->insert(['id' => $auditId]);
    },
    $counts,
    false,
));
