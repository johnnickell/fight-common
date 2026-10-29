<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeRejected;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeRemovalRejected;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeStorageFailed;
use Fight\Common\Domain\Mcp\Notice\NoticeRemovalSelection;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeRemovalFixture;

require dirname(__DIR__, 4).'/vendor/autoload.php';

$config = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$role = $argv[2]; $directory = dirname($argv[1]);
$connection = DriverManager::getConnection($config['params']);
if ($config['params']['driver'] === 'pdo_sqlite') { $connection->executeStatement('PRAGMA busy_timeout = 10000'); }
$proxy = Mockery::mock(Connection::class);
foreach (['getDatabasePlatform', 'isTransactionActive', 'getTransactionIsolation', 'getTransactionNestingLevel', 'beginTransaction', 'executeStatement', 'fetchOne', 'fetchAssociative', 'fetchAllAssociative', 'insert', 'update', 'delete'] as $method) {
    $proxy->shouldReceive($method)->andReturnUsing(static fn (...$arguments) => $connection->$method(...$arguments));
}
$barrier = static function () use ($role, $directory, $connection): void {
    if ($role !== 'first') { return; }
    file_put_contents($directory.'/first-locked', json_encode(['high' => (int) $connection->fetchOne('SELECT last_position FROM fight_notice_state'), 'lost' => (int) $connection->fetchOne('SELECT lost_through FROM fight_notice_state')], JSON_THROW_ON_ERROR));
    $deadline = microtime(true) + 15;
    while (!file_exists($directory.'/release')) {
        if (microtime(true) > $deadline) { throw new RuntimeException('Worker barrier timeout.'); }
        usleep(10000);
    }
};
$proxy->shouldReceive('commit')->andReturnUsing(static function () use ($barrier, $role, $config, $connection): void {
    $barrier();
    if ($role === 'first' && $config['mode'] === 'remove-rollback-retry') { throw new RuntimeException('Injected failure before commit.'); }
    $connection->commit();
});
$proxy->shouldReceive('rollBack')->andReturnUsing(static function () use ($barrier, $connection): void { $barrier(); $connection->rollBack(); });
$fixture = new NoticeRemovalFixture($proxy);
$fixture->onAdmit = static function () use ($directory, $role): void {
    file_put_contents($directory.'/'.$role.'-admitted', 'removal');
    if ($role === 'second' && file_exists($directory.'/revoked')) { throw new NoticeRemovalRejected(); }
};
$fixture->recording->onAdmit = static function () use ($directory, $role): void {
    file_put_contents($directory.'/'.$role.'-admitted', 'recording');
    if ($role === 'second' && file_exists($directory.'/revoked')) { throw new NoticeRejected(); }
};
$mode = $config['mode'];
$operation = $role === 'first' ? 'remove' : 'retry';
if (in_array($mode, ['record-first-remove', 'record-first-revoked-remove'], true)) { $operation = $role === 'first' ? 'distinct' : 'remove'; }
if ($mode === 'retry-first-remove') { $operation = $role === 'first' ? 'retry' : 'remove'; }
if ($mode === 'conflict-first-remove') { $operation = $role === 'first' ? 'conflict' : 'remove'; }
if ($role === 'second') {
    $operation = match ($mode) { 'remove-first-repeat' => 'remove', 'remove-first-distinct' => 'distinct', 'remove-first-conflicting' => 'conflict', 'remove-first-window' => 'window', default => $operation };
}
file_put_contents($directory.'/'.$role.'-started', $operation);
$status = 'ok'; $uncertain = null; $window = null;
try {
    if ($operation === 'remove') { $fixture->remove(NoticeRemovalSelection::fromArray($config['selection'])); }
    elseif ($operation === 'window') {
        $snapshot = $fixture->recording->store->readWindow('scope', 0, 100);
        $window = ['positions' => array_map(static fn ($row) => $row->position(), $snapshot->notices()), 'high' => $snapshot->highWater(), 'lost' => $snapshot->lostThrough()];
    } else {
        $changes = match ($operation) { 'distinct' => ['id' => 'notice-3'], 'conflict' => ['topic' => 'changed'], default => [] };
        $fixture->recording->record($changes);
    }
} catch (Throwable $failure) {
    $status = $failure::class;
    if ($failure instanceof NoticeStorageFailed) { $uncertain = $failure->outcomeUncertain(); }
}
echo json_encode(['status' => $status, 'uncertain' => $uncertain, 'recordAdmissions' => $fixture->recording->admissions, 'removalAdmissions' => $fixture->admissions, 'window' => $window], JSON_THROW_ON_ERROR);
$connection->close(); Mockery::close();
