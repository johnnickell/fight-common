<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\Task\TaskCheckConnection;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;

require __DIR__.'/../../../../vendor/autoload.php';
$config = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$directory = dirname($argv[1]); $role = $argv[2]; $mode = $config['mode'];
$driver = DriverManager::getConnection($config['params'])->getDriver();
$connection = new TaskCheckConnection($config['params'], $driver);
if ($config['params']['driver'] === 'pdo_sqlite') { $connection->executeStatement('PRAGMA busy_timeout = 10000'); }
$connection->beforeCommit = static function () use ($role, $directory): void {
    if ($role !== 'first') { return; }
    file_put_contents($directory.'/first-precommit', 'ready');
    $deadline = microtime(true) + 15;
    while (!file_exists($directory.'/release')) {
        if (microtime(true) > $deadline) { throw new RuntimeException('Task input barrier timeout'); }
        usleep(10000);
    }
};
$f = new TaskFixture($connection); $events = 0;
$f->events->addHandler(\Fight\Common\Domain\Utility\ClassName::underscore(\Fight\Common\Domain\Mcp\Task\Event\TaskChanged::class), static function () use (&$events): void { ++$events; });
$admission = static function () use ($f, $directory, $role): void {
    file_put_contents($directory.'/'.$role.'-admitted', 'yes');
    if ($role === 'second' && file_exists($directory.'/revoked')) { $f->submitAllowed = false; $f->continueAllowed = false; $f->updateAllowed = false; }
};
$f->onInputAdmission = $admission; $f->onAdmission = $admission;
file_put_contents($directory.'/'.$role.'-started', 'yes');
$trace = getenv('FIGHT_COMMON_TASK_INPUT_TRACE');
if (is_string($trace) && $trace !== '') {
    file_put_contents($trace, json_encode(['phase' => 'started', 'pid' => getmypid(), 'parent' => function_exists('posix_getppid') ? posix_getppid() : null, 'backend' => $config['params']['driver'], 'mode' => $mode, 'role' => $role], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
}
try {
    $terminal = in_array($mode, ['completed-first', 'failed-first', 'cancelled-first'], true) && $role === 'first';
    if ($terminal || ($mode === 'input-first' && $role === 'second')) {
        $state = match ($mode) {
            'failed-first' => TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])),
            'cancelled-first' => TaskState::create('cancelled'),
            default => TaskFixture::completed()
        };
        $f->record($state, 2);
    } elseif ($mode === 'acquire') {
        $f->acquire();
    } elseif ($mode === 'refusal' && $role === 'first') {
        $f->supply([TaskFixture::KEY_1 => ['action' => 'decline']]);
    } elseif ($mode === 'partial-refusal' && $role === 'second') {
        $f->supply([TaskFixture::KEY_2 => ['action' => 'cancel']]);
    } else {
        $answer = $role === 'second' && $mode === 'conflict' ? 'conflicting-second' : 'first-answer';
        $f->supply([TaskFixture::KEY_1 => ['action' => 'accept', 'content' => (object) ['answer' => $answer]]]);
    }
    $status = 'committed';
} catch (Throwable $failure) { $status = $failure::class; }
$result = ['status' => $status, 'events' => $events, 'pid' => getmypid()];
if (is_string($trace) && $trace !== '') {
    file_put_contents($trace, json_encode(['phase' => 'finished', 'backend' => $config['params']['driver'], 'mode' => $mode, 'role' => $role, ...$result], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
}
echo json_encode($result, JSON_THROW_ON_ERROR);
$connection->close();
