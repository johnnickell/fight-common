<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Execution\Dbal\DbalObservationRepository;
use Fight\Common\Adapter\Execution\Dbal\DbalRecordingTransaction;
use Fight\Common\Domain\Execution\Detail\ProtectedContent;
use Fight\Common\Domain\Execution\Observation\Event\ObservationRecorded;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\Execution\RecordingFixture;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$config = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$role = $argv[2];
$directory = dirname($argv[1]);
$connection = DriverManager::getConnection($config['params']);
$store = new DbalRecordingTransaction($connection);
if ($connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform) {
    $connection->executeStatement('PRAGMA busy_timeout = 10000');
}
$fixture = new RecordingFixture($store);
$fixture->setOnAdmit(static function () use ($directory, $role): void {
    file_put_contents($directory.'/'.$role.'-admitted', 'admitted');
    if ($role === 'first') {
        $deadline = microtime(true) + 10;
        while (!file_exists($directory.'/release')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Race barrier timed out.');
            }
            usleep(10000);
        }
    }
});
$events = 0;
$fixture->events()->addHandler(ClassName::underscore(ObservationRecorded::class), static function (EventMessage $message) use (&$events, $connection): void {
    if ($connection->isTransactionActive()) {
        throw new RuntimeException('Dispatch occurred before the real commit.');
    }
    ++$events;
});
$data = $config['observation'];
$content = $config['content'] ?? null;
if ($role === 'second' && $config['conflict']) {
    if ($data['detail'] !== null) {
        if ($config['metadata_conflict'] ?? false) {
            $data['detail']['content_type'] = 'text/other';
        } else {
            $content['body'] = 'changed-private-body';
        }
    } elseif ($data['activity'] !== null) {
        if ($config['usage_conflict'] ?? false) {
            $data['activity']['usage'][2]['value'] = '999';
        } else {
            $data['activity']['summary'] = 'Changed safe summary';
        }
    } else {
        $data['outcome'] = 'failed';
    }
}
$observation = Observation::fromArray($data);
file_put_contents($directory.'/'.$role.'-started', 'started');
$status = 'recorded';
try {
    if (($config['removal_role'] ?? null) === $role) {
        // Storage fixture only: future authorized removal must use the same boundary.
        $store->commitTransactional(static function () use ($connection, $observation, $directory, $role): void {
            file_put_contents($directory.'/'.$role.'-admitted', 'storage lock held');
            if ($role === 'first') {
                $deadline = microtime(true) + 10;
                while (!file_exists($directory.'/release')) {
                    if (microtime(true) > $deadline) { throw new RuntimeException('Removal race barrier timed out.'); }
                    usleep(10000);
                }
            }
            $connection->delete('fight_execution_contents', ['producer_id' => $observation->producerId(), 'observation_id' => $observation->id()->toString()]);
        });
        $status = 'removed';
    } else {
        $fixture->record($observation, $content === null ? null : ProtectedContent::fromArray($observation->detail(), $content));
    }
} catch (Throwable $throwable) {
    $status = $throwable::class;
}
$stored = (new DbalObservationRepository($store))->getById($observation->producerId(), $observation->id());
echo json_encode([
    'status' => $status,
    'events' => $events,
    'admissions' => $fixture->admissions(),
    'recorded_at' => $stored?->recordedAt()->format('Y-m-d\TH:i:s.u\Z'),
    'outcome' => $stored?->outcome()->value,
    'id' => $stored?->id()->toString(),
    'payload' => $stored?->toArray(),
], JSON_THROW_ON_ERROR).PHP_EOL;
