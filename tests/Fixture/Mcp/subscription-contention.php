<?php

declare(strict_types=1);

require dirname(__DIR__, 3).'/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Http\Mcp\McpResponseEmitter;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Common\Domain\Notice\Query\RecoverNotices;
use Fight\Test\Common\TestCase\Mcp\SubscriptionFixture;
use Fight\Test\Common\TestCase\Notice\NoticeFixture;
use Symfony\Component\Process\Process;

[$script, $directory, $mode, $transport] = $argv;
$db = DriverManager::getConnection(json_decode(file_get_contents($directory.'/connection.json'), true, flags: JSON_THROW_ON_ERROR));
if ($db->getParams()['driver'] === 'pdo_sqlite') { $db->executeStatement('PRAGMA busy_timeout = 7000'); }
if ($mode === 'timeout') {
    match ($db->getParams()['driver']) {
        'pdo_sqlite' => $db->executeStatement('PRAGMA busy_timeout = 1000'),
        'pdo_mysql' => $db->executeStatement('SET SESSION innodb_lock_wait_timeout = 1'),
        'pdo_pgsql' => $db->executeStatement("SET lock_timeout = '1s'")
    };
}
$f = new SubscriptionFixture($db, lifetime: 20);
$response = $f->endpoint->handle(SubscriptionFixture::request(id: '7'));
$body = $response->getBody();
echo $body->read(65536);
(new NoticeFixture($db))->record(['id' => 'retained', 'type' => 'mcp.tools.list_changed']);
$original = $db->fetchAssociative('SELECT observed_at, expires_at, closed_at FROM fight_mcp_subscriptions');
// Bound the successful emitter after its first batch; the subsequent source failure is an intentional stop.
$reads = 0;
$f->queryPolicy = static function () use (&$reads): void { if (++$reads > 1) { throw new RuntimeException('Fixture stop after first live batch'); } };
$remaining = $mode === 'fast' ? 3.5 : 0.8;
while (microtime(true) < (int) $original['expires_at'] - $remaining) { usleep(1000); }
$hold = $mode === 'overlong' ? 5200000 : 2200000;
$worker = new Process([PHP_BINARY, __DIR__.'/subscription-lock-worker.php', $directory, (string) $hold]);
try {
    $worker->start();
    $deadline = microtime(true) + 3;
    while (!file_exists($directory.'/lock-ready')) {
        if (microtime(true) >= $deadline) { throw new RuntimeException('Lock worker did not acquire capacity lock: '.$worker->getErrorOutput()); }
        usleep(1000);
    }
    $started = microtime(true);
    $position = $body->tell();
    if ($transport === 'emitter') { (new McpResponseEmitter())->emitBody($response); }
    else { echo $body->read(65536); }
    $returned = microtime(true);
    $exit = $worker->wait();
    $row = $db->fetchAssociative('SELECT observed_at, expires_at, closed_at FROM fight_mcp_subscriptions');
    $result = [
        'original' => $original, 'started' => $started, 'returned' => $returned,
        'locked' => json_decode(file_get_contents($directory.'/locked-state.json'), true, flags: JSON_THROW_ON_ERROR),
        'row' => $row, 'eof' => $body->eof(), 'position_before' => $position, 'position_after' => $body->tell(),
        'diagnostics' => $f->diagnostics, 'worker_exit' => $exit, 'worker_error' => $worker->getErrorOutput(),
        'transaction_active' => $db->isTransactionActive(),
        'retained' => (int) $db->fetchOne('SELECT COUNT(*) FROM fight_notices')
    ];
    $body->close();
    $f->queryPolicy = null;
    $recovery = $f->queries->fetch(new RecoverNotices(NoticeSelection::create(['scope'])));
    $result['recovery'] = ['status' => $recovery->status(), 'ids' => array_map(static fn ($view) => $view->notice()->id(), $recovery->notices())];
    file_put_contents($directory.'/contention-result.json', json_encode($result, JSON_THROW_ON_ERROR));
} finally {
    $worker->stop(1);
    $body->close();
    $db->close();
}
