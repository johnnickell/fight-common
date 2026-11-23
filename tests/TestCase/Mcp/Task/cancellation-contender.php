<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTables;
use Fight\Common\Domain\Mcp\Task\Command\AcquireTaskInput;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Command\SupplyTaskInput;
use Fight\Common\Domain\Mcp\Task\Event\TaskChanged;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;

require __DIR__.'/../../../../vendor/autoload.php';

$config = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$directory = dirname($argv[1]);
$connection = DriverManager::getConnection($config['params']);
if ($config['params']['driver'] === 'pdo_sqlite') {
    $connection->executeStatement('PRAGMA busy_timeout = 10000');
}
$connection->fetchOne('SELECT 1');
$fixture = new TaskFixture($connection, new DbalTaskTables(...$config['tables']));
$events = 0;
$fixture->eventDispatcher->addHandler(ClassName::underscore(TaskChanged::class), static function () use (&$events): void {
    ++$events;
});
$admit = static function () use ($fixture, $directory): void {
    file_put_contents($directory.'/admitted', 'yes');
    if (is_file($directory.'/revoked')) {
        $fixture->cancelAllowed = false;
        $fixture->continueAllowed = false;
        $fixture->submitAllowed = false;
    }
};
$fixture->onCancellationAdmission = $admit;
$fixture->onInputAdmission = $admit;
$command = match ($config['operation']) {
    'cancel'  => new RequestTaskCancellation('scope', TaskFixture::id(), 'caller'),
    'acquire' => new AcquireTaskInput('scope', TaskFixture::id(), 'caller', TaskFixture::KEY_1),
    'supply'  => new SupplyTaskInput('scope', TaskFixture::id(), 'caller', StrictJson::fromObject([
        TaskFixture::KEY_1 => ['action' => 'accept', 'content' => (object) ['answer' => 'accepted']]
    ]))
};
$handler = match ($config['operation']) {
    'cancel'  => $fixture->requestTaskCancellationHandler,
    'acquire' => $fixture->acquireTaskInputHandler,
    'supply'  => $fixture->supplyTaskInputHandler
};
file_put_contents($directory.'/started', 'yes');
try {
    $handler->handle(CommandMessage::create($command));
    $outcome = 'committed';
} catch (Throwable $failure) {
    $outcome = $failure::class;
}
echo json_encode(['outcome' => $outcome, 'events' => $events, 'active' => $connection->isTransactionActive()], JSON_THROW_ON_ERROR);
$connection->close();
