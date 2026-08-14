<?php

declare(strict_types=1);

use Fight\Common\Application\Repository\UnitOfWork;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\ConnectionInterface;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/../shared.php';

final class LaravelUnitOfWork implements UnitOfWork
{
    private bool $active = false;

    public function __construct(private readonly ConnectionInterface $connection) {}

    public function commit(): void
    {
        throw new LogicException('Laravel records execute immediately; no pending-change context exists.');
    }

    public function commitTransactional(callable $operation): mixed
    {
        if ($this->active) {
            throw new LogicException('Nested transactions are outside the portable contract.');
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
$schema->create('sessions', static function ($table): void {
    $table->string('id')->primary();
});
$schema->create('audits', static function ($table): void {
    $table->string('id')->primary();
});

$factory = static fn (): UnitOfWork => new LaravelUnitOfWork($connection);
$write = static function (string $sessionId, string $auditId) use ($connection): void {
    $connection->table('sessions')->insert(['id' => $sessionId]);
    $connection->table('audits')->insert(['id' => $auditId]);
};
$counts = static fn (): array => [
    'sessions' => $connection->table('sessions')->count(),
    'audits' => $connection->table('audits')->count(),
];

printReceipt(runTransactionProbe(
    'Laravel native DB transaction',
    ['illuminate/database' => Composer\InstalledVersions::getPrettyVersion('illuminate/database')],
    $factory(),
    $write,
    $counts,
    $factory,
    'not natural: record writes execute immediately',
));
