<?php

declare(strict_types=1);

require dirname(__DIR__, 3).'/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Http\McpResponseEmitter;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionStore;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Mcp\Notice\Query\RecoverNotices;
use Fight\Test\Common\Fixture\Mcp\CoordinatedSubscriptionConnection;
use Fight\Test\Common\Fixture\Mcp\ObservedSubscriptionStore;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\Mcp\SubscriptionFixture;
use Symfony\Component\Process\Process;

[$script, $directory, $mode, $transport] = $argv;
$connectionParameters = json_decode(file_get_contents($directory.'/connection.json'), true, flags: JSON_THROW_ON_ERROR);
$connectionParameters['wrapperClass'] = CoordinatedSubscriptionConnection::class;
$subscriptionConnection = DriverManager::getConnection($connectionParameters);
if ($connectionParameters['driver'] === 'pdo_sqlite') { $subscriptionConnection->executeStatement('PRAGMA busy_timeout = 7000'); }
if ($mode === 'timeout') {
    match ($connectionParameters['driver']) {
        'pdo_sqlite' => $subscriptionConnection->executeStatement('PRAGMA busy_timeout = 1000'),
        'pdo_mysql' => $subscriptionConnection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1'),
        'pdo_pgsql' => $subscriptionConnection->executeStatement("SET lock_timeout = '1s'")
    };
}
$observedSubscriptionStore = null;
$subscriptionFixture = new SubscriptionFixture($subscriptionConnection, lifetime: 20, subscriptionStoreDecorator: static function (McpSubscriptionStore $subscriptionStore) use (&$observedSubscriptionStore): McpSubscriptionStore {
    return $observedSubscriptionStore = new ObservedSubscriptionStore($subscriptionStore);
});
$response = $subscriptionFixture->endpoint->handle(SubscriptionFixture::request(id: '7'));
$body = $response->getBody();
echo $body->read(65536);
(new NoticeFixture($subscriptionConnection))->record(['id' => 'retained', 'type' => 'mcp.tools.list_changed']);
$original = $subscriptionConnection->fetchAssociative('SELECT * FROM fight_mcp_subscriptions');
$observedSubscriptionStore->calls = [];
// Bound the successful emitter after its first batch; the subsequent source failure is an intentional stop.
$sourceReads = 0;
$subscriptionFixture->queryPolicy = static function () use (&$sourceReads): void { if (++$sourceReads > 1) { throw new RuntimeException('Fixture stop after first live batch'); } };
$holdMicroseconds = $mode === 'overlong' ? 5200000 : 2200000;
$lockWorker = new Process([PHP_BINARY, __DIR__.'/subscription-lock-worker.php', $directory, (string) $holdMicroseconds]);
$lockWorker->setTimeout(10);
$subscriptionConnection->beforeTransaction = static function () use ($subscriptionConnection, $observedSubscriptionStore, $lockWorker, $directory): void {
    $callIndex = array_key_last($observedSubscriptionStore->calls);
    // This callback is inside DbalSubscriptionStore::heartbeat/close, not before execution's freshness check.
    $observedSubscriptionStore->calls[$callIndex]['transaction_entered_at'] = microtime(true);
    if (!$lockWorker->isStarted()) {
        $lockWorker->start();
        $deadline = microtime(true) + 3;
        while (!file_exists($directory.'/lock-ready')) {
            if (microtime(true) >= $deadline) { throw new RuntimeException('Lock worker did not acquire capacity lock: '.$lockWorker->getErrorOutput()); }
            usleep(1000);
        }
    }
    $observedSubscriptionStore->calls[$callIndex]['lock_ready_at'] = microtime(true);
};
try {
    // Resume expiry cases with two seconds left, not the old scheduling-sensitive 0.8-second process-start window.
    // The worker starts only AFTER actual store entry; no lock-ready/elapsed-time inference identifies the operation.
    $resumeAt = (int) $original['expires_at'] - ($mode === 'fast' ? 3.5 : 2);
    if ($mode === 'pre-renewal-expired') { $resumeAt = (int) $original['expires_at']; }
    while (microtime(true) < $resumeAt) { usleep(1000); }
    $started = microtime(true);
    $position = $body->tell();
    if ($transport === 'emitter') { (new McpResponseEmitter())->emitBody($response); }
    else { echo $body->read(65536); }
    $returned = microtime(true);
    if (!$lockWorker->isStarted()) { throw new RuntimeException('No observed store operation started the lock worker'); }
    $exit = $lockWorker->wait();
    $row = $subscriptionConnection->fetchAssociative('SELECT * FROM fight_mcp_subscriptions');
    $result = [
        'original' => $original, 'started' => $started, 'returned' => $returned,
        'calls' => $observedSubscriptionStore->calls,
        'locked' => json_decode(file_get_contents($directory.'/locked-state.json'), true, flags: JSON_THROW_ON_ERROR),
        'row' => $row, 'eof' => $body->eof(), 'position_before' => $position, 'position_after' => $body->tell(),
        'diagnostics' => $subscriptionFixture->diagnostics, 'worker_exit' => $exit, 'worker_error' => $lockWorker->getErrorOutput(),
        'transaction_active' => $subscriptionConnection->isTransactionActive(),
        'retained' => (int) $subscriptionConnection->fetchOne('SELECT COUNT(*) FROM fight_notices')
    ];
    $body->close();
    $subscriptionFixture->queryPolicy = null;
    $recovery = $subscriptionFixture->queries->fetch(new RecoverNotices(NoticeSelection::create(['scope'])));
    $result['recovery'] = ['status' => $recovery->status(), 'ids' => array_map(static fn ($noticeView) => $noticeView->notice()->id(), $recovery->notices())];
    file_put_contents($directory.'/contention-result.json', json_encode($result, JSON_THROW_ON_ERROR));
} finally {
    $lockWorker->stop(1);
    $body->close();
    $subscriptionConnection->close();
}
