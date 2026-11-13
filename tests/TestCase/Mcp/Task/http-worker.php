<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTables;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\LifecycleTables;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;

require __DIR__.'/../../../../vendor/autoload.php';

$config = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$connection = DriverManager::getConnection($config['params']);
$lifecycleTables = new LifecycleTables($config['tables'] ?? []);
// The ordinary Task HTTP journey also accepts its existing two-table configuration.
$tables = isset($config['tables']) ? $lifecycleTables->task : new DbalTaskTables($config['stateTable'] ?? 'fight_task_state', $config['taskTable'] ?? 'fight_tasks');
$fixture = new TaskFixture($connection, $tables);
$mode = $argv[2];
$notice = $mode === 'notice' ? new NoticeFixture($connection, $lifecycleTables->notice) : null;
if (isset($argv[3])) {
    // Prepare an independent supplier without reading a Task version or performing its operation early.
    $connection->executeQuery('SELECT 1')->fetchOne();
    $ready = $argv[3].'.ready';
    $temporary = $ready.'.'.getmypid().'.tmp';
    $payload = json_encode(['pid' => getmypid(), 'readyAt' => hrtime(true)], JSON_THROW_ON_ERROR);
    try {
        // The parent must see either no marker or complete JSON, never a file still being written.
        if (file_put_contents($temporary, $payload) !== strlen($payload)) { throw new RuntimeException('Cannot write Task supplier readiness'); }
        if (!rename($temporary, $ready)) { throw new RuntimeException('Cannot publish Task supplier readiness'); }
    } finally {
        if (file_exists($temporary) && !unlink($temporary)) { throw new RuntimeException('Cannot remove temporary Task supplier readiness'); }
    }
    $deadline = microtime(true) + 10;
    do {
        clearstatcache(true, $argv[3].'.release');
        if (file_exists($argv[3].'.release')) { break; }
        if (microtime(true) >= $deadline) { throw new RuntimeException('Task supplier was not released'); }
        usleep(1000);
    } while (true);
}
$attempted = false;
if ($mode === 'notice') { $notice->record(['id' => 'live-catalog', 'type' => 'mcp.tools.list_changed']); }
elseif ($mode === 'input') { $fixture->issue(confirmation: true, count: 2); }
elseif ($mode === 'continue') {
    $fixture->acquire();
    // Independent consumer fixture, not a Common worker: acquisition never performs this effect itself.
    $attempted = true;
    $fixture->record(TaskFixture::completed(true), $fixture->get()->get('version'));
} elseif ($mode === 'observe') {
    // Protected observation does not perform stopping or change state.
} else {
    $state = match ($mode) {
        'failed' => TaskState::create('failed', error: StrictJson::fromObject(['code' => -32603, 'message' => 'Internal error'])),
        'cancelled' => TaskState::create('cancelled'),
        default => TaskFixture::completed()
    };
    $fixture->record($state, $fixture->get()->get('version'));
}
$late = null;
if (in_array($mode, ['continue', 'completed', 'failed', 'cancelled'], true)) {
    try { $fixture->record(TaskState::create('working'), $fixture->get()->get('version')); }
    catch (TaskConflict $failure) { $late = $failure::class; }
}
echo json_encode(['pid' => getmypid(), 'view' => $fixture->get(), 'input' => $fixture->observe(), 'cancellation' => $fixture->cancellation(), 'effect_attempted' => $attempted, 'late' => $late], JSON_THROW_ON_ERROR);
