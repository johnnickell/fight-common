<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Subscription\DbalSubscriptionStore;
use Fight\Test\Common\TestCase\Notice\NoticeFixture;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$params = json_decode(file_get_contents($argv[1].'/connection.json'), true, flags: JSON_THROW_ON_ERROR);
$db = DriverManager::getConnection($params);
if ($params['driver'] === 'pdo_sqlite') { $db->executeStatement('PRAGMA busy_timeout = 3000'); }
$data = json_decode(file_get_contents($argv[1].'/operation.json'), true, flags: JSON_THROW_ON_ERROR);
if ($data['operation'] === 'record') {
    $writer = new NoticeFixture($db);
    foreach ($data['notices'] as $notice) { $writer->record($notice); }
} else {
    $store = new DbalSubscriptionStore($db);
    $method = $data['operation'];
    $store->$method(...$data['arguments']);
}
echo json_encode(['status' => 'committed', 'process' => getmypid()], JSON_THROW_ON_ERROR);
