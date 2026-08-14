<?php

declare(strict_types=1);

use Fight\Common\Application\Repository\UnitOfWork;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Db\Sqlite\Dsn;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/../shared.php';

final class YiiUnitOfWork implements UnitOfWork
{
    private bool $active = false;

    public function __construct(private readonly ConnectionInterface $connection) {}

    public function commit(): void
    {
        throw new LogicException('Yii DB writes execute immediately; no pending-change context exists.');
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

$connection = new Connection(
    new Driver(new Dsn(databaseName: 'memory')),
    new SchemaCache(new Psr16Cache(new ArrayAdapter())),
);
$connection->createCommand('CREATE TABLE sessions (id VARCHAR(80) PRIMARY KEY)')->execute();
$connection->createCommand('CREATE TABLE audits (id VARCHAR(80) PRIMARY KEY)')->execute();

$factory = static fn (): UnitOfWork => new YiiUnitOfWork($connection);
$write = static function (string $sessionId, string $auditId) use ($connection): void {
    $connection->createCommand()->insert('sessions', ['id' => $sessionId])->execute();
    $connection->createCommand()->insert('audits', ['id' => $auditId])->execute();
};
$counts = static fn (): array => [
    'sessions' => (int) $connection->createCommand('SELECT COUNT(*) FROM sessions')->queryScalar(),
    'audits' => (int) $connection->createCommand('SELECT COUNT(*) FROM audits')->queryScalar(),
];

printReceipt(runTransactionProbe(
    'Yii DB native transaction',
    ['yiisoft/db' => Composer\InstalledVersions::getPrettyVersion('yiisoft/db')],
    $factory(),
    $write,
    $counts,
    $factory,
    'not natural: record writes execute immediately',
));
