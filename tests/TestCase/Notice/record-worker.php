<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\Common\Application\Notice\Exception\NoticeRejected;
use Fight\Common\Domain\Notice\Command\RecordNotice;
use Fight\Common\Domain\Notice\Event\NoticeRecorded;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\Notice\NoticeFixture;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$config = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$role = $argv[2]; $directory = dirname($argv[1]);
$connection = DriverManager::getConnection($config['params']);
if ($config['params']['driver'] === 'pdo_sqlite') { $connection->executeStatement('PRAGMA busy_timeout = 10000'); }
$proxy = Mockery::mock(Connection::class);
foreach (['getDatabasePlatform', 'isTransactionActive', 'getTransactionIsolation', 'getTransactionNestingLevel', 'beginTransaction', 'executeStatement', 'fetchOne', 'fetchAssociative', 'insert', 'update', 'rollBack'] as $method) {
    $proxy->shouldReceive($method)->andReturnUsing(static fn (...$arguments) => $connection->$method(...$arguments));
}
$proxy->shouldReceive('commit')->andReturnUsing(static function () use ($role, $directory, $config, $connection): void {
    if ($role === 'first') {
        file_put_contents($directory.'/first-precommit', (string) $connection->fetchOne('SELECT last_position FROM fight_notice_state'));
        $deadline = microtime(true) + 15;
        while (!file_exists($directory.'/release')) {
            if (microtime(true) > $deadline) { throw new RuntimeException('Worker barrier timeout.'); }
            usleep(10000);
        }
        if ($config['mode'] === 'rollback') { throw new RuntimeException('Injected failure before actual commit.'); }
    }
    $connection->commit();
});
$fixture = new NoticeFixture($proxy);
$fixture->onAdmit = static function () use ($directory, $role): void {
    file_put_contents($directory.'/'.$role.'-admitted', 'yes');
    if ($role === 'second' && file_exists($directory.'/revoked')) { throw new NoticeRejected(); }
};
$events = 0;
$fixture->events->addHandler(ClassName::underscore(NoticeRecorded::class), static function () use (&$events): void { ++$events; });
$data = NoticeFixture::data();
if ($role === 'second') {
    if (in_array($config['mode'], ['distinct', 'rollback'], true)) { $data['id'] = 'notice-2'; }
    if ($config['mode'] === 'conflict') { $data['topic'] = 'changed'; }
}
file_put_contents($directory.'/'.$role.'-started', 'yes');
$status = 'recorded'; $uncertain = null;
try { $fixture->bus->execute(RecordNotice::fromArray($data)); }
catch (Throwable $failure) { $status = $failure::class; if ($failure instanceof \Fight\Common\Application\Notice\Exception\NoticeStorageFailed) { $uncertain = $failure->outcomeUncertain(); } }
echo json_encode(['status' => $status, 'events' => $events, 'admissions' => $fixture->admissions, 'uncertain' => $uncertain], JSON_THROW_ON_ERROR);
$connection->close(); Mockery::close();
