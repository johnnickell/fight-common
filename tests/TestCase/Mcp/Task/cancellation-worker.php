<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTables;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\Task\TaskCheckConnection;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;

require __DIR__.'/../../../../vendor/autoload.php';
$config = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$directory = dirname($argv[1]); $role = $argv[2];
$connection = new TaskCheckConnection($config['params'], DriverManager::getConnection($config['params'])->getDriver());
if ($config['params']['driver'] === 'pdo_sqlite') { $connection->executeStatement('PRAGMA busy_timeout = 10000'); }
$connection->beforeCommit = static function () use ($role, $directory): void {
    if ($role !== 'first') { return; }
    file_put_contents($directory.'/first-precommit', 'ready'); $deadline = microtime(true) + 15;
    while (!file_exists($directory.'/release')) {
        if (microtime(true) > $deadline) { throw new RuntimeException('Cancellation barrier timeout'); }
        usleep(10000);
    }
};
$tables = new DbalTaskTables($config['stateTable'], $config['taskTable']);
$fixture = new TaskFixture($connection, $tables); $events = 0;
$fixture->eventDispatcher->addHandler(ClassName::underscore(TaskChanged::class), static function () use (&$events): void { ++$events; });
$admission = static function () use ($fixture, $role, $directory): void {
    file_put_contents($directory.'/'.$role.'-admitted', 'yes');
    if ($role === 'second' && file_exists($directory.'/revoked')) { $fixture->cancelAllowed = false; $fixture->continueAllowed = false; $fixture->submitAllowed = false; }
};
$fixture->onCancellationAdmission = $admission; $fixture->onInputAdmission = $admission; $fixture->onAdmission = $admission;
file_put_contents($directory.'/'.$role.'-started', 'yes');
$operation = $config[$role];
try {
    match ($operation) {
        'cancel' => $fixture->cancel(),
        'supply' => $fixture->supply([TaskFixture::KEY_1 => ['action' => 'accept', 'content' => (object) ['answer' => 'accepted']]]),
        'acquire' => $fixture->acquire(),
        'issue' => $fixture->issue(expectedVersion: $config['version']),
        'completed' => $fixture->record(TaskFixture::completed(), $config['version']),
        'failed' => $fixture->record(TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])), $config['version']),
        'cancelled' => $fixture->record(TaskState::create('cancelled'), $config['version']),
        'observe' => null
    };
    $status = 'committed';
} catch (Throwable $failure) { $status = $failure::class; }
$observed = $fixture->cancellation(); $input = $fixture->observe();
$result = ['status' => $status, 'events' => $events, 'pid' => getmypid(), 'observation' => $observed, 'input' => $input, 'operation' => $operation, 'backend' => $config['params']['driver']];
$trace = getenv('FIGHT_COMMON_TASK_CANCELLATION_TRACE');
if (is_string($trace) && $trace !== '') { file_put_contents($trace, json_encode($result, JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX); }
echo json_encode($result, JSON_THROW_ON_ERROR);
$connection->close();
