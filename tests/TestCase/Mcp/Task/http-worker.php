<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;

require __DIR__.'/../../../../vendor/autoload.php';

$config = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$connection = DriverManager::getConnection($config['params']);
$fixture = new TaskFixture($connection);
$mode = $argv[2];
$attempted = false;
if ($mode === 'input') { $fixture->issue(confirmation: true, count: 2); }
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
