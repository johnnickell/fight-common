<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalObservationTables;
use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalRecordingTransaction;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
use Fight\Common\Domain\Mcp\Observation\Event\ObservationRecorded;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\Mcp\Execution\RecordingFixture;
use Fight\Test\Common\TestCase\Mcp\Execution\RemovalFixture;

require dirname(__DIR__, 4).'/vendor/autoload.php';

$config = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$role = $argv[2]; $directory = dirname($argv[1]); $operation = $config['operations'][$role];
$connection = DriverManager::getConnection($config['params']);
if ($connection->getDatabasePlatform() instanceof SQLitePlatform) { $connection->executeStatement('PRAGMA busy_timeout = 10000'); }
$tables = new DbalObservationTables(...($config['tables'] ?? []));
$contentTable = $tables->contentTable()->quoted($connection->getDatabasePlatform());
$recordingTransaction = new DbalRecordingTransaction($connection, $tables);
$barrier = static function () use ($directory): void {
    $deadline = microtime(true) + 10;
    while (!file_exists($directory.'/release')) {
        if (microtime(true) > $deadline) { throw new RuntimeException('Removal barrier timed out.'); }
        usleep(10000);
    }
};
$admitted = static function () use ($directory, $role, $config, $barrier): void {
    file_put_contents($directory.'/'.$role.'-admitted', 'common serialization lock held');
    if ($role === 'first' && !($config['pause_after_delete'] ?? false)) { $barrier(); }
};
$afterDelete = null;
if ($config['pause_after_delete'] ?? false) {
    $afterDelete = static function () use ($directory, $role, $barrier, $connection, $config, $contentTable): void {
        if ((int) $connection->fetchOne('SELECT COUNT(*) FROM '.$contentTable.' WHERE observation_id = ?', [$config['observation']['observation_id']]) !== 0) {
            throw new RuntimeException('Expected actual deletion before barrier.');
        }
        file_put_contents($directory.'/'.$role.'-deleted', 'uncommitted deletion'); $barrier();
    };
}
$remove = new RemovalFixture($recordingTransaction, $afterDelete); $remove->onAdmit = $admitted;
$record = new RecordingFixture($recordingTransaction); $record->setOnAdmit($admitted);
$events = 0;
$record->events()->addHandler(ClassName::underscore(ObservationRecorded::class), static function () use (&$events, $connection): void {
    if ($connection->isTransactionActive()) { throw new RuntimeException('Event preceded the real commit.'); }
    ++$events;
});
$data = $config['observation']; $content = $config['content'] ?? null;
if ($operation === 'body-conflict') { $content['body'] = 'changed-private-body'; }
if ($operation === 'metadata-conflict') { $data['detail']['content_type'] = 'text/other'; }
$observation = Observation::fromArray($data);
file_put_contents($directory.'/'.$role.'-started', 'attempting operation');
try {
    if ($operation === 'remove') { $remove->commandBus->execute(RemovalFixture::command($observation)); $status = 'removed'; }
    else { $record->record($observation, ProtectedContent::fromArray($observation->detail(), $content)); $status = 'recorded'; }
} catch (Throwable $failure) { $status = $failure::class; }
echo json_encode(['status' => $status, 'admissions' => $remove->admissions + $record->admissions(), 'events' => $events], JSON_THROW_ON_ERROR).PHP_EOL;
