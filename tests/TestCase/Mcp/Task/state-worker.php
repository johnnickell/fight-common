<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTables;
use Fight\Common\Application\Mcp\Task\Service\TaskInputForms;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Domain\Mcp\Task\Command\CreateTask;
use Fight\Common\Domain\Mcp\Task\Command\RecordTaskState;
use Fight\Common\Domain\Mcp\Task\Command\RemoveTasks;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskInput;
use Fight\Common\Domain\Mcp\Task\TaskSelection;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Mockery as M;

require __DIR__.'/../../../../vendor/autoload.php';

$config = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$directory = dirname($argv[1]); $role = $argv[2]; $mode = $config['mode'];
$connection = DriverManager::getConnection($config['params']);
if ($config['params']['driver'] === 'pdo_sqlite') { $connection->executeStatement('PRAGMA busy_timeout = 10000'); }
$proxy = M::mock(Connection::class);
foreach (['getDatabasePlatform', 'isTransactionActive', 'isAutoCommit', 'getTransactionIsolation', 'getTransactionNestingLevel', 'beginTransaction', 'executeStatement', 'fetchOne', 'delete', 'insert', 'rollBack'] as $method) {
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
$tables = new DbalTaskTables($config['stateTable'], $config['taskTable']);
$fixture = new TaskFixture($proxy, $tables); $eventCount = 0;
foreach ([\Fight\Common\Domain\Mcp\Task\Event\TaskChanged::class, \Fight\Common\Domain\Mcp\Task\Event\TaskDataRemoved::class] as $eventClass) {
    $fixture->eventDispatcher->addHandler(\Fight\Common\Domain\Utility\ClassName::underscore($eventClass), static function () use (&$eventCount): void { ++$eventCount; });
}
$fixture->onAdmission = static function () use ($fixture, $directory, $role): void {
    file_put_contents($directory.'/'.$role.'-admitted', 'ready');
    if ($role === 'second' && file_exists($directory.'/revoked')) { $fixture->createAllowed = false; $fixture->updateAllowed = false; $fixture->removeAllowed = false; }
};
$create = $fixture->create(...);
$remove = $fixture->remove(...);
$record = $fixture->record(...);
if ($config['direct'] ?? false) {
    $create = static fn (?int $ttl = null) => $fixture->createTaskHandler->handle(CommandMessage::create(
        new CreateTask(TaskFixture::definition(), TaskState::create('working'), $ttl)
    ));
    $remove = static fn () => $fixture->removeTasksHandler->handle(CommandMessage::create(
        new RemoveTasks(TaskSelection::fromIds('scope', [TaskFixture::id()]))
    ));
    $record = static function (TaskState $state) use ($fixture): void {
        if ($state->status() === 'input_required') {
            $forms = McpInputRequired::fromRequests(['form-0' => TaskFixture::form()]);
            $fixture->requestTaskInputHandler->handle(CommandMessage::create(new RequestTaskInput(
                'scope', TaskFixture::id(), 'producer', 1, TaskInputForms::batch($forms, 'caller', $fixture->now + 1000)
            )));
            return;
        }
        $fixture->recordTaskStateHandler->handle(CommandMessage::create(
            new RecordTaskState('scope', TaskFixture::id(), 'producer', 1, $state)
        ));
    };
}
file_put_contents($directory.'/'.$role.'-started', 'ready');
try {
    if (in_array($mode, ['remove-progress', 'remove-outcome', 'update-remove', 'create-remove', 'remove-repeat', 'remove-revoked', 'remove-rollback-update', 'remove-create'], true)) {
        $removes = $role === 'first'
            ? !in_array($mode, ['update-remove', 'create-remove'], true)
            : in_array($mode, ['update-remove', 'create-remove', 'remove-repeat', 'remove-revoked'], true);
        if ($removes) { $remove(); }
        elseif (($role === 'first' && $mode === 'create-remove') || ($role === 'second' && $mode === 'remove-create')) { $create(); }
        else { $record($mode === 'remove-progress' ? TaskState::create('working', 'Late progress') : TaskFixture::completed()); }
    } elseif (in_array($mode, ['duplicate', 'conflict', 'rollback', 'revoked'], true)) {
        $create($role === 'second' && $mode === 'conflict' ? 1000 : null);
    } else {
        $state = TaskFixture::completed(true);
        if ($role === 'second' && $mode === 'late-input') { $state = TaskFixture::input(); }
        if ($role === 'second' && $mode === 'late-progress') { $state = TaskState::create('working', 'Delayed progress'); }
        if ($role === 'second' && $mode === 'late-cancel') { $state = TaskState::create('cancelled'); }
        $record($state);
    }
    $status = 'committed';
} catch (Throwable $failure) { $status = $failure::class; }
echo json_encode(['status' => $status, 'events' => $eventCount, 'admissions' => $fixture->admissions], JSON_THROW_ON_ERROR);
M::close();
