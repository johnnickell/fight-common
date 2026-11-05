<?php

declare(strict_types=1);

$bootStartedAt = microtime(true);
// Deterministically model slow bootstrap before loading any application dependency.
usleep((int) ($argv[3] ?? 0));
require dirname(__DIR__, 3).'/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

// A clean, prewarmed process owns only the capacity lock, never an inherited stream/Fiber.
$directory = $argv[1];
$coordinated = ($argv[4] ?? '') === 'coordinated';
$subscriptionConnection = DriverManager::getConnection(json_decode(file_get_contents($directory.'/connection.json'), true, flags: JSON_THROW_ON_ERROR));
$publish = static function (string $file, array $data) use ($directory): void {
    file_put_contents($directory.'/'.$file.'.tmp', json_encode($data, JSON_THROW_ON_ERROR));
    rename($directory.'/'.$file.'.tmp', $directory.'/'.$file);
};
try {
    $subscriptionConnection->fetchOne('SELECT 1');
    match ($subscriptionConnection->getParams()['driver']) {
        'pdo_sqlite' => $subscriptionConnection->executeStatement('PRAGMA busy_timeout = 5000'),
        'pdo_mysql' => $subscriptionConnection->executeStatement('SET SESSION innodb_lock_wait_timeout = 5'),
        'pdo_pgsql' => $subscriptionConnection->executeStatement("SET lock_timeout = '5s'")
    };
    $publish('worker-ready.json', ['boot_started' => $bootStartedAt, 'prepared_at' => microtime(true)]);
    if ($coordinated) {
        echo "worker-ready\n";
        flush();
        $requestDeadline = microtime(true) + 20;
        while (!file_exists($directory.'/lock-request')) {
            if (microtime(true) >= $requestDeadline) { throw new RuntimeException('No tracking operation requested the capacity lock'); }
            usleep(1000);
        }
    }
    $subscriptionConnection->beginTransaction();
    $subscriptionConnection->executeStatement('UPDATE fight_mcp_subscription_state SET version = version WHERE singleton = 1');
    $publish('lock-ready.json', ['acquired_at' => microtime(true)]);
    // The request already came from inside actual store entry; no second competing entry handshake is necessary.
    touch($directory.'/lock-ready');
    usleep((int) $argv[2]);
    $row = $subscriptionConnection->fetchAssociative('SELECT * FROM fight_mcp_subscriptions');
    $publish('locked-state.json', ['at' => microtime(true), 'row' => $row]);
    $subscriptionConnection->commit();
    $publish('lock-released.json', ['released_at' => microtime(true)]);
} finally {
    if ($subscriptionConnection->isTransactionActive()) { $subscriptionConnection->rollBack(); }
    $subscriptionConnection->close();
}
