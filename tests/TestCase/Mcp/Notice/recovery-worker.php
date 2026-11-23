<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeRecoveryReader;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTables;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Mcp\Notice\Query\RecoverNotices;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeRecoveryFixture;

require dirname(__DIR__, 4).'/vendor/autoload.php';

$config = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$role = $argv[2]; $directory = dirname($argv[1]);
$connection = DriverManager::getConnection($config['params']);
if ($config['params']['driver'] === 'pdo_sqlite') { $connection->executeStatement('PRAGMA busy_timeout = 10000'); }
$proxy = Mockery::mock(Connection::class);
foreach (['getDatabasePlatform', 'isTransactionActive', 'getTransactionIsolation', 'isAutoCommit', 'getTransactionNestingLevel', 'beginTransaction', 'executeStatement', 'fetchOne', 'fetchAssociative', 'fetchAllAssociative', 'rollBack'] as $method) {
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
$tables = new DbalNoticeTables(...($config['tables'] ?? []));
file_put_contents($directory.'/'.$role.'-started', 'recovery');
if (isset($config['continuationRace'])) {
    $fixture = new NoticeRecoveryFixture($proxy, tables: $tables);
    $batch = $fixture->queryBus->fetch(new RecoverNotices(NoticeSelection::create(['scope']), $config['continuationRace']['token'], 1));
    echo json_encode(['status' => 'ok', 'recordAdmissions' => 0, 'removalAdmissions' => 0, 'batch' => $batch->toArray()], JSON_THROW_ON_ERROR);
} elseif ($config['direct'] ?? false) {
    $window = (new DbalNoticeRecoveryReader($proxy, $tables))->readWindow('scope', $config['after'], 1);
    echo json_encode([
        'status' => 'ok', 'recordAdmissions' => 0, 'removalAdmissions' => 0,
        'window' => ['positions' => array_map(static fn ($row) => $row->position(), $window->notices()), 'high' => $window->highWater(), 'lost' => $window->lostThrough()]
    ], JSON_THROW_ON_ERROR);
} else {
    $fixture = new NoticeRecoveryFixture($proxy, tables: $tables);
    echo json_encode($fixture->call($config['arguments']), JSON_THROW_ON_ERROR);
}
$connection->close(); Mockery::close();
