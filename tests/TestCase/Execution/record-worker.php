<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Execution\Dbal\DbalObservationStore;
use Fight\Common\Domain\Execution\Observation;
use Fight\Common\Domain\Execution\ObservationRecorded;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\Execution\RecordingFixture;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$config = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$role = $argv[2];
$directory = dirname($argv[1]);
$connection = DriverManager::getConnection($config['params']);
$store = new DbalObservationStore($connection);
if ($connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform) {
    $connection->executeStatement('PRAGMA busy_timeout = 10000');
}
$fixture = new RecordingFixture($store);
$fixture->onAdmit = static function () use ($directory, $role): void {
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
};
$events = 0;
$fixture->events->addHandler(ClassName::underscore(ObservationRecorded::class), static function (EventMessage $message) use (&$events, $connection): void {
    if ($connection->isTransactionActive()) {
        throw new RuntimeException('Dispatch occurred before the real commit.');
    }
    ++$events;
});
$data = $config['observation'];
if ($role === 'second' && $config['conflict']) {
    $data['outcome'] = 'failed';
}
$observation = Observation::fromArray($data);
file_put_contents($directory.'/'.$role.'-started', 'started');
$status = 'recorded';
try {
    $fixture->record($observation);
} catch (Throwable $throwable) {
    $status = $throwable::class;
}
$stored = $store->get($observation->producerId, $observation->id);
echo json_encode([
    'status' => $status,
    'events' => $events,
    'admissions' => $fixture->admissions,
    'recorded_at' => $stored?->recordedAt->format('Y-m-d\TH:i:s.u\Z'),
    'outcome' => $stored?->observation->outcome->value,
    'id' => $stored?->observation->id->toString(),
], JSON_THROW_ON_ERROR).PHP_EOL;
