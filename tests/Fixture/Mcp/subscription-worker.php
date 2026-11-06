<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal\DbalSubscriptionStore;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$params = json_decode(file_get_contents($argv[1].'/connection.json'), true, flags: JSON_THROW_ON_ERROR);
$connection = DriverManager::getConnection($params);
if ($params['driver'] === 'pdo_sqlite') { $connection->executeStatement('PRAGMA busy_timeout = 3000'); }
$data = json_decode(file_get_contents($argv[1].'/operation.json'), true, flags: JSON_THROW_ON_ERROR);
if ($data['operation'] === 'record') {
    $writer = new NoticeFixture($connection);
    foreach ($data['notices'] as $notice) { $writer->record($notice); }
} else {
    $subscriptionStore = new DbalSubscriptionStore($connection);
    $method = $data['operation'];
    $subscriptionStore->$method(...$data['arguments']);
}
echo json_encode(['status' => 'committed', 'process' => getmypid()], JSON_THROW_ON_ERROR);
