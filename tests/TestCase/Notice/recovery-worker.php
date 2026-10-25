<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\Test\Common\TestCase\Notice\NoticeRecoveryFixture;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$config = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$role = $argv[2]; $directory = dirname($argv[1]);
$connection = DriverManager::getConnection($config['params']);
if ($config['params']['driver'] === 'pdo_sqlite') { $connection->executeStatement('PRAGMA busy_timeout = 10000'); }
$proxy = Mockery::mock(Connection::class);
foreach (['getDatabasePlatform', 'isTransactionActive', 'getTransactionIsolation', 'getTransactionNestingLevel', 'beginTransaction', 'executeStatement', 'fetchOne', 'fetchAssociative', 'fetchAllAssociative', 'rollBack'] as $method) {
    $proxy->shouldReceive($method)->andReturnUsing(static fn (...$arguments) => $connection->$method(...$arguments));
}
$proxy->shouldReceive('commit')->andReturnUsing(static function () use ($role, $directory, $connection): void {
    if ($role === 'first') {
        file_put_contents($directory.'/first-locked', 'recovery snapshot');
        $deadline = microtime(true) + 15;
        while (!file_exists($directory.'/release')) {
            if (microtime(true) > $deadline) { throw new RuntimeException('Worker barrier timeout.'); }
            usleep(10000);
        }
    }
    $connection->commit();
});
$fixture = new NoticeRecoveryFixture($proxy);
file_put_contents($directory.'/'.$role.'-started', 'recovery');
echo json_encode($fixture->call($config['arguments']), JSON_THROW_ON_ERROR);
$connection->close(); Mockery::close();
