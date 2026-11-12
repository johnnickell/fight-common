<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Test\Common\Fixture\Mcp\ContentionObservations;
use Fight\Test\Common\TestCase\Mcp\LifecycleTables;

$bootStartedAt = microtime(true);
$directory = $argv[1];
require __DIR__.'/ContentionObservations.php';
$workerPhases = [];
$observationErrors = [];
$observeWorkerPhase = static function (string $phase) use ($directory, &$workerPhases, &$observationErrors): void {
    $workerPhases[$phase] = ['at' => microtime(true), 'monotonic_ns' => hrtime(true), 'pid' => getmypid()];
    $error = ContentionObservations::write($directory.'/worker-phases.json', $workerPhases + ['observation_errors' => $observationErrors]);
    if ($error !== null) { $observationErrors[$phase] = $error; }
};
$observeWorkerPhase('bootstrap_started');
register_shutdown_function(static function () use ($observeWorkerPhase): void { $observeWorkerPhase('shutdown'); });
// Deterministically model slow bootstrap before loading any application dependency.
usleep((int) ($argv[3] ?? 0));
require dirname(__DIR__, 3).'/vendor/autoload.php';

// A clean, prewarmed process owns only the capacity lock, never an inherited stream/Fiber.
$observeWorkerPhase('autoload_returned');
$coordinated = ($argv[4] ?? '') === 'coordinated';
$tables = new LifecycleTables(is_file($directory.'/tables.json') ? json_decode(file_get_contents($directory.'/tables.json'), true, flags: JSON_THROW_ON_ERROR) : []);
$subscriptionConnection = DriverManager::getConnection(json_decode(file_get_contents($directory.'/connection.json'), true, flags: JSON_THROW_ON_ERROR));
$publish = static function (string $file, array $data) use ($directory): void {
    file_put_contents($directory.'/'.$file.'.tmp', json_encode($data, JSON_THROW_ON_ERROR));
    rename($directory.'/'.$file.'.tmp', $directory.'/'.$file);
};
$primaryFailure = null;
try {
    $observeWorkerPhase('connect_started');
    $subscriptionConnection->fetchOne('SELECT 1');
    $observeWorkerPhase('connect_returned');
    match ($subscriptionConnection->getParams()['driver']) {
        'pdo_sqlite' => $subscriptionConnection->executeStatement('PRAGMA busy_timeout = 5000'),
        'pdo_mysql' => $subscriptionConnection->executeStatement('SET SESSION innodb_lock_wait_timeout = 5'),
        'pdo_pgsql' => $subscriptionConnection->executeStatement("SET lock_timeout = '5s'")
    };
    $observeWorkerPhase('prepared');
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
    $observeWorkerPhase('lock_requested');
    $subscriptionConnection->beginTransaction();
    $subscriptionConnection->executeStatement($tables->sql($subscriptionConnection, 'UPDATE fight_mcp_subscription_state SET version = version WHERE singleton = 1'));
    $observeWorkerPhase('lock_acquired');
    $publish('lock-ready.json', ['acquired_at' => microtime(true)]);
    // The request already came from inside actual store entry; no second competing entry handshake is necessary.
    touch($directory.'/lock-ready');
    usleep((int) $argv[2]);
    $row = $subscriptionConnection->fetchAssociative($tables->sql($subscriptionConnection, 'SELECT * FROM fight_mcp_subscriptions'));
    $publish('locked-state.json', ['at' => microtime(true), 'row' => $row]);
    $observeWorkerPhase('commit_started');
    $subscriptionConnection->commit();
    $observeWorkerPhase('lock_released');
    $publish('lock-released.json', ['released_at' => microtime(true)]);
} catch (Throwable $failure) {
    $primaryFailure = $failure;
    $observeWorkerPhase('failed');
    throw $failure;
} finally {
    $cleanupFailure = null;
    try {
        if ($subscriptionConnection->isTransactionActive()) { $subscriptionConnection->rollBack(); }
    } catch (Throwable $failure) { $cleanupFailure = $failure; $observeWorkerPhase('rollback_failed'); }
    try { $subscriptionConnection->close(); } catch (Throwable $failure) { $cleanupFailure ??= $failure; $observeWorkerPhase('connection_close_failed'); }
    $observeWorkerPhase('connection_closed_attempted');
    if ($primaryFailure === null && $cleanupFailure !== null) { throw $cleanupFailure; }
}
