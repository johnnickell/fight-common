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
$bootstrapDelayMicroseconds = (int) ($argv[4] ?? 0);
$holdMicroseconds = $mode === 'overlong' ? 5200000 : 2200000;
$lockWorker = new Process([PHP_BINARY, __DIR__.'/subscription-lock-worker.php', $directory, (string) $holdMicroseconds, (string) $bootstrapDelayMicroseconds, 'coordinated'], timeout: 25);
$body = null;
try {
    $lockWorker->start();
    // Preparation precedes any lease under the explicit 25-second worker watchdog; lock acquisition remains bounded at 3s.
    $startupOutput = '';
    $workerIsReady = $lockWorker->waitUntil(static function (string $type, string $output) use (&$startupOutput): bool {
        if ($type === Process::OUT) { $startupOutput .= $output; }
        return str_contains($startupOutput, "worker-ready\n");
    });
    if (!$workerIsReady) { throw new RuntimeException('Lock worker ended before readiness: '.$lockWorker->getErrorOutput().$lockWorker->getOutput()); }
    $workerReady = json_decode(file_get_contents($directory.'/worker-ready.json'), true, flags: JSON_THROW_ON_ERROR);
    $registrationStartedAt = microtime(true);
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
    $subscriptionConnection->beforeTransaction = static function () use ($observedSubscriptionStore, $subscriptionConnection, $directory): void {
        $callIndex = array_key_last($observedSubscriptionStore->calls);
        // Notice recovery can also open transactions; only an in-flight tracking call owns this observation.
        if ($callIndex === null || isset($observedSubscriptionStore->calls[$callIndex]['result'])) { return; }
        $subscriptionConnection->operation = $observedSubscriptionStore->calls[$callIndex]['operation'];
        // This callback is inside DbalSubscriptionStore::heartbeat/close, after execution's freshness check.
        $observedSubscriptionStore->calls[$callIndex]['transaction_entered_at'] = microtime(true);
        if (!file_exists($directory.'/lock-request')) {
            touch($directory.'/lock-request');
            $deadline = microtime(true) + 3;
            while (!file_exists($directory.'/lock-ready')) {
                if (microtime(true) >= $deadline) { throw new RuntimeException('Lock worker did not acquire capacity lock'); }
                usleep(1000);
            }
        }
        $observedSubscriptionStore->calls[$callIndex]['lock_ready_at'] = microtime(true);
    };
    // Two seconds of the original lease remain for renewal; the warmed worker takes its lock only after real store entry.
    $resumeAt = (int) $original['expires_at'] - ($mode === 'fast' ? 3.5 : 2);
    if ($mode === 'pre-renewal-expired') { $resumeAt = (int) $original['expires_at']; }
    $resumeDeadline = hrtime(true) + 6000000000;
    while (microtime(true) < $resumeAt) {
        if (hrtime(true) >= $resumeDeadline) { throw new RuntimeException('Fixture clock did not reach the bounded renewal target'); }
        usleep(1000);
    }
    $started = microtime(true);
    if ($mode !== 'pre-renewal-expired' && $started >= (int) $original['expires_at']) { throw new RuntimeException('Fixture missed the pre-renewal freshness window'); }
    $position = $body->tell();
    if ($transport === 'emitter') { (new McpResponseEmitter())->emitBody($response); }
    else { echo $body->read(65536); }
    $returned = microtime(true);
    if (!file_exists($directory.'/lock-request')) { throw new RuntimeException('No observed store operation requested the capacity lock'); }
    $exit = $lockWorker->wait();
    $row = $subscriptionConnection->fetchAssociative('SELECT * FROM fight_mcp_subscriptions');
    $result = [
        'original' => $original, 'started' => $started, 'returned' => $returned,
        'bootstrap_delay_us' => $bootstrapDelayMicroseconds, 'worker_prepared' => $workerReady,
        'worker_ready_at' => $workerReady['prepared_at'], 'registration_started_at' => $registrationStartedAt,
        'worker_lock' => json_decode(file_get_contents($directory.'/lock-ready.json'), true, flags: JSON_THROW_ON_ERROR),
        'worker_release' => json_decode(file_get_contents($directory.'/lock-released.json'), true, flags: JSON_THROW_ON_ERROR),
        'calls' => $observedSubscriptionStore->calls, 'capacity_locks' => $subscriptionConnection->capacityLocks,
        'locked' => json_decode(file_get_contents($directory.'/locked-state.json'), true, flags: JSON_THROW_ON_ERROR),
        'row' => $row, 'eof' => $body->eof(), 'position_before' => $position, 'position_after' => $body->tell(),
        'diagnostics' => $subscriptionFixture->diagnostics, 'worker_exit' => $exit, 'worker_error' => $lockWorker->getErrorOutput(),
        'worker_output' => $lockWorker->getOutput(),
        'transaction_active' => $subscriptionConnection->isTransactionActive(),
        'retained' => (int) $subscriptionConnection->fetchOne('SELECT COUNT(*) FROM fight_notices')
    ];
    $body->close();
    $subscriptionFixture->queryPolicy = null;
    $recovery = $subscriptionFixture->queryBus->fetch(new RecoverNotices(NoticeSelection::create(['scope'])));
    $result['recovery'] = ['status' => $recovery->status(), 'ids' => array_map(static fn ($noticeView) => $noticeView->notice()->id(), $recovery->notices())];
    file_put_contents($directory.'/contention-result.json', json_encode($result, JSON_THROW_ON_ERROR));
} catch (Throwable $failure) {
    file_put_contents($directory.'/contention-failure.json', json_encode([
        'failure' => $failure::class, 'message' => $failure->getMessage(),
        'worker_exit' => $lockWorker->getExitCode(), 'worker_output' => $lockWorker->getOutput(),
        'worker_error' => $lockWorker->getErrorOutput(), 'calls' => $observedSubscriptionStore->calls ?? [],
        'capacity_locks' => $subscriptionConnection->capacityLocks
    ], JSON_THROW_ON_ERROR));
    throw $failure;
} finally {
    $lockWorker->stop(1);
    $body?->close();
    $subscriptionConnection->close();
}
