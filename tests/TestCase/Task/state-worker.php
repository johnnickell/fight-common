<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\Common\Domain\Task\TaskState;
use Fight\Test\Common\TestCase\Task\TaskFixture;
use Mockery as M;

require __DIR__.'/../../../vendor/autoload.php';

$config = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$directory = dirname($argv[1]); $role = $argv[2]; $mode = $config['mode'];
$connection = DriverManager::getConnection($config['params']);
if ($config['params']['driver'] === 'pdo_sqlite') { $connection->executeStatement('PRAGMA busy_timeout = 10000'); }
$proxy = M::mock(Connection::class);
foreach (['getDatabasePlatform', 'isTransactionActive', 'getTransactionIsolation', 'getTransactionNestingLevel', 'beginTransaction', 'executeStatement', 'fetchOne', 'delete', 'insert', 'rollBack'] as $method) {
    $proxy->shouldReceive($method)->andReturnUsing(static fn (...$arguments) => $connection->$method(...$arguments));
}
$proxy->shouldReceive('commit')->andReturnUsing(static function () use ($connection, $directory, $role, $mode): void {
    if ($role === 'first') {
        file_put_contents($directory.'/first-precommit', 'ready');
        $deadline = microtime(true) + 15;
        while (!file_exists($directory.'/release')) {
            if (microtime(true) > $deadline) { throw new RuntimeException('Barrier timeout'); }
            usleep(10000);
        }
        if (in_array($mode, ['rollback', 'remove-rollback-update'], true)) { throw new RuntimeException('Injected precommit failure'); }
    }
    $connection->commit();
});
$fixture = new TaskFixture($proxy); $events = 0;
foreach ([\Fight\Common\Domain\Task\Event\TaskChanged::class, \Fight\Common\Domain\Task\Event\TaskDataRemoved::class] as $event) {
    $fixture->events->addHandler(\Fight\Common\Domain\Utility\ClassName::underscore($event), static function () use (&$events): void { ++$events; });
}
$fixture->onAdmission = static function () use ($fixture, $directory, $role): void {
    file_put_contents($directory.'/'.$role.'-admitted', 'ready');
    if ($role === 'second' && file_exists($directory.'/revoked')) { $fixture->createAllowed = false; $fixture->updateAllowed = false; $fixture->removeAllowed = false; }
};
file_put_contents($directory.'/'.$role.'-started', 'ready');
try {
    if (in_array($mode, ['remove-progress', 'remove-outcome', 'update-remove', 'create-remove', 'remove-repeat', 'remove-revoked', 'remove-rollback-update', 'remove-create'], true)) {
        $removes = $role === 'first'
            ? !in_array($mode, ['update-remove', 'create-remove'], true)
            : in_array($mode, ['update-remove', 'create-remove', 'remove-repeat', 'remove-revoked'], true);
        if ($removes) { $fixture->remove(); }
        elseif (($role === 'first' && $mode === 'create-remove') || ($role === 'second' && $mode === 'remove-create')) { $fixture->create(); }
        else { $fixture->record($mode === 'remove-progress' ? TaskState::create('working', 'Late progress') : TaskFixture::completed()); }
    } elseif (in_array($mode, ['duplicate', 'conflict', 'rollback', 'revoked'], true)) {
        $fixture->create($role === 'second' && $mode === 'conflict' ? 1000 : null);
    } else {
        $state = TaskFixture::completed(true);
        if ($role === 'second' && $mode === 'late-input') { $state = TaskFixture::input(); }
        if ($role === 'second' && $mode === 'late-progress') { $state = TaskState::create('working', 'Delayed progress'); }
        if ($role === 'second' && $mode === 'late-cancel') { $state = TaskState::create('cancelled'); }
        $fixture->record($state);
    }
    $status = 'committed';
} catch (Throwable $failure) { $status = $failure::class; }
echo json_encode(['status' => $status, 'events' => $events, 'admissions' => $fixture->admissions], JSON_THROW_ON_ERROR);
M::close();
