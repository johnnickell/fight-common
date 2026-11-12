<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal\DbalSubscriptionStore;
use Fight\Test\Common\TestCase\Mcp\LifecycleTables;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$params = json_decode(file_get_contents($argv[1].'/connection.json'), true, flags: JSON_THROW_ON_ERROR);
$connection = DriverManager::getConnection($params);
$tables = new LifecycleTables(json_decode(file_get_contents($argv[1].'/tables.json'), true, flags: JSON_THROW_ON_ERROR));
if ($params['driver'] === 'pdo_sqlite') { $connection->executeStatement('PRAGMA busy_timeout = 3000'); }
if (($argv[2] ?? '') === 'prepared') {
    // Deliberately slow producer bootstrap must complete before a finite live subscription opens.
    usleep(2200000);
    $connection->executeQuery('SELECT 1')->fetchOne();
    file_put_contents($argv[1].'/producer-ready', 'yes');
    $deadline = microtime(true) + 5;
    while (!file_exists($argv[1].'/release-producer')) {
        clearstatcache();
        if (microtime(true) > $deadline) { throw new RuntimeException('Prepared producer release unavailable'); }
        usleep(10000);
    }
}
$data = json_decode(file_get_contents($argv[1].'/operation.json'), true, flags: JSON_THROW_ON_ERROR);
if ($data['operation'] === 'record') {
    $writer = new NoticeFixture($connection, $tables->notice);
    foreach ($data['notices'] as $notice) { $writer->record($notice); }
} else {
    $subscriptionStore = new DbalSubscriptionStore($connection, tables: $tables->subscription);
    $method = $data['operation'];
    $subscriptionStore->$method(...$data['arguments']);
}
echo json_encode(['status' => 'committed', 'process' => getmypid()], JSON_THROW_ON_ERROR);
