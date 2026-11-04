<?php

declare(strict_types=1);

require dirname(__DIR__, 3).'/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

// A clean process owns only the capacity lock, never an inherited stream/Fiber.
$directory = $argv[1];
$db = DriverManager::getConnection(json_decode(file_get_contents($directory.'/connection.json'), true, flags: JSON_THROW_ON_ERROR));
if (($argv[3] ?? '') === 'coordinated') {
    // Finish process/driver startup before the stream opens its five-second lease, without taking its lock yet.
    $db->fetchOne('SELECT 1');
    file_put_contents($directory.'/worker-ready', json_encode(['at' => microtime(true)], JSON_THROW_ON_ERROR));
    echo "worker-ready\n";
    flush();
    $requestDeadline = microtime(true) + 20;
    while (!file_exists($directory.'/lock-request')) {
        if (microtime(true) >= $requestDeadline) { throw new RuntimeException('No tracking operation requested the capacity lock'); }
        usleep(1000);
    }
}
$db->beginTransaction();
try {
    $db->executeStatement('UPDATE fight_mcp_subscription_state SET version = version WHERE singleton = 1');
    file_put_contents($directory.'/lock-ready', 'ready');
    usleep((int) $argv[2]);
    $row = $db->fetchAssociative('SELECT * FROM fight_mcp_subscriptions');
    file_put_contents($directory.'/locked-state.json', json_encode(['at' => microtime(true), 'row' => $row], JSON_THROW_ON_ERROR));
    $db->commit();
} finally {
    if ($db->isTransactionActive()) { $db->rollBack(); }
    $db->close();
}
