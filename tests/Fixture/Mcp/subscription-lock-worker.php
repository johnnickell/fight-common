<?php

declare(strict_types=1);

$bootStarted = microtime(true);
// Deterministically model a slow process bootstrap before loading any application dependency.
usleep((int) ($argv[3] ?? 0));
require dirname(__DIR__, 3).'/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

// A clean, prewarmed process owns only the capacity lock, never an inherited stream/Fiber.
$directory = $argv[1];
$synchronized = ($argv[4] ?? null) === 'synchronized';
$db = DriverManager::getConnection(json_decode(file_get_contents($directory.'/connection.json'), true, flags: JSON_THROW_ON_ERROR));
$await = static function (string $file) use ($directory): void {
    $deadline = hrtime(true) + 10000000000;
    while (!file_exists($directory.'/'.$file)) {
        if (hrtime(true) >= $deadline) { throw new RuntimeException('Lock worker timed out awaiting '.$file); }
        usleep(1000);
    }
};
$publish = static function (string $file, array $data) use ($directory): void {
    file_put_contents($directory.'/'.$file.'.tmp', json_encode($data, JSON_THROW_ON_ERROR));
    rename($directory.'/'.$file.'.tmp', $directory.'/'.$file);
};
try {
    $db->fetchOne('SELECT 1');
    match ($db->getParams()['driver']) {
        'pdo_sqlite' => $db->executeStatement('PRAGMA busy_timeout = 5000'),
        'pdo_mysql' => $db->executeStatement('SET SESSION innodb_lock_wait_timeout = 5'),
        'pdo_pgsql' => $db->executeStatement("SET lock_timeout = '5s'")
    };
    $publish('worker-ready.json', ['boot_started' => $bootStarted, 'prepared_at' => microtime(true)]);
    if ($synchronized) { $await('lock-request'); }
    $db->beginTransaction();
    $db->executeStatement('UPDATE fight_mcp_subscription_state SET version = version WHERE singleton = 1');
    $publish('lock-ready.json', ['acquired_at' => microtime(true)]);
    // Journey holds start at actual heartbeat/close entry; direct store tests retain their original handshake.
    if ($synchronized) { $await('operation-entered.json'); }
    else { touch($directory.'/lock-ready'); }
    usleep((int) $argv[2]);
    $row = $db->fetchAssociative('SELECT observed_at, expires_at, closed_at FROM fight_mcp_subscriptions');
    $publish('locked-state.json', ['at' => microtime(true), 'row' => $row]);
    $db->commit();
    $publish('lock-released.json', ['released_at' => microtime(true)]);
} finally {
    if ($db->isTransactionActive()) { $db->rollBack(); }
    $db->close();
}
