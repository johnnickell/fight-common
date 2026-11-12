<?php

declare(strict_types=1);

require dirname(__DIR__, 3).'/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Http\McpResponseEmitter;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionStore;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Mcp\Notice\Query\RecoverNotices;
use Fight\Test\Common\Fixture\Mcp\ContentionObservations;
use Fight\Test\Common\Fixture\Mcp\CoordinatedSubscriptionConnection;
use Fight\Test\Common\Fixture\Mcp\ObservedSubscriptionStore;
use Fight\Test\Common\TestCase\Mcp\LifecycleTables;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\Mcp\SubscriptionFixture;
use Symfony\Component\Process\Process;

[$script, $directory, $mode, $transport] = $argv;
$connectionParameters = json_decode(file_get_contents($directory.'/connection.json'), true, flags: JSON_THROW_ON_ERROR);
$connectionParameters['wrapperClass'] = CoordinatedSubscriptionConnection::class;
$subscriptionConnection = DriverManager::getConnection($connectionParameters);
$primaryFailure = null;
$bootstrapDelayMicroseconds = (int) ($argv[4] ?? 0);
$holdMicroseconds = $mode === 'overlong' ? 5200000 : 2200000;
$lockWorker = new Process([PHP_BINARY, __DIR__.'/subscription-lock-worker.php', $directory, (string) $holdMicroseconds, (string) $bootstrapDelayMicroseconds, 'coordinated'], timeout: 25);
$body = null;
$observedSubscriptionStore = null;
$phaseObservations = [];
$observePhase = static function (string $phase) use (&$phaseObservations): void {
    $phaseObservations[$phase] = ['at' => microtime(true), 'monotonic_ns' => hrtime(true)];
};
try {
    $tables = new LifecycleTables(json_decode(file_get_contents($directory.'/tables.json'), true, flags: JSON_THROW_ON_ERROR));
    $subscriptionConnection->capacityLockSql = $tables->sql($subscriptionConnection, 'UPDATE fight_mcp_subscription_state SET version = version WHERE singleton = 1');
    if ($connectionParameters['driver'] === 'pdo_sqlite') { $subscriptionConnection->executeStatement('PRAGMA busy_timeout = 7000'); }
    if ($mode === 'timeout') {
        match ($connectionParameters['driver']) {
            'pdo_sqlite' => $subscriptionConnection->executeStatement('PRAGMA busy_timeout = 1000'),
            'pdo_mysql' => $subscriptionConnection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1'),
            'pdo_pgsql' => $subscriptionConnection->executeStatement("SET lock_timeout = '1s'")
        };
    }
    $observePhase('worker_start_requested');
    $lockWorker->start();
    $observePhase('worker_started');
    // Preparation precedes any lease under the explicit 25-second worker watchdog; lock acquisition remains bounded at 3s.
    $startupOutput = '';
    $workerIsReady = $lockWorker->waitUntil(static function (string $type, string $output) use (&$startupOutput): bool {
        if ($type === Process::OUT) { $startupOutput .= $output; }
        return str_contains($startupOutput, "worker-ready\n");
    });
    if (!$workerIsReady) { throw new RuntimeException('Lock worker ended before readiness: '.$lockWorker->getErrorOutput().$lockWorker->getOutput()); }
    $workerReady = json_decode(file_get_contents($directory.'/worker-ready.json'), true, flags: JSON_THROW_ON_ERROR);
    $observePhase('worker_ready_observed');
    $registrationStartedAt = microtime(true);
    $observePhase('registration_started');
    $subscriptionFixture = new SubscriptionFixture($subscriptionConnection, lifetime: 20, subscriptionStoreDecorator: static function (McpSubscriptionStore $subscriptionStore) use (&$observedSubscriptionStore): McpSubscriptionStore {
        return $observedSubscriptionStore = new ObservedSubscriptionStore($subscriptionStore);
    }, noticeTables: $tables->notice, subscriptionTables: $tables->subscription);
    $response = $subscriptionFixture->endpoint->handle(SubscriptionFixture::request(id: '7'));
    $observePhase('endpoint_returned');
    $body = $response->getBody();
    echo $body->read(65536);
    $observePhase('acknowledgment_read');
    (new NoticeFixture($subscriptionConnection, $tables->notice))->record(['id' => 'retained', 'type' => 'mcp.tools.list_changed']);
    $observePhase('notice_recorded');
    $original = $subscriptionConnection->fetchAssociative($tables->sql($subscriptionConnection, 'SELECT * FROM fight_mcp_subscriptions'));
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
    $observePhase('renewal_target_reached');
    $started = microtime(true);
    if ($mode !== 'pre-renewal-expired' && $started >= (int) $original['expires_at']) { throw new RuntimeException('Fixture missed the pre-renewal freshness window'); }
    $position = $body->tell();
    if ($transport === 'emitter') { (new McpResponseEmitter())->emitBody($response); }
    else { echo $body->read(65536); }
    $returned = microtime(true);
    $observePhase('transport_returned');
    if (!file_exists($directory.'/lock-request')) { throw new RuntimeException('No observed store operation requested the capacity lock'); }
    $observePhase('worker_wait_started');
    $exit = $lockWorker->wait();
    $observePhase('worker_wait_returned');
    $row = $subscriptionConnection->fetchAssociative($tables->sql($subscriptionConnection, 'SELECT * FROM fight_mcp_subscriptions'));
    $result = [
        'phases' => $phaseObservations, 'worker_started_at' => $lockWorker->getStartTime(),
        'worker_phases' => ContentionObservations::read($directory.'/worker-phases.json'),
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
        'retained' => (int) $subscriptionConnection->fetchOne($tables->sql($subscriptionConnection, 'SELECT COUNT(*) FROM fight_notices'))
    ];
    $body->close();
    $subscriptionFixture->queryPolicy = null;
    $recovery = $subscriptionFixture->queryBus->fetch(new RecoverNotices(NoticeSelection::create(['scope'])));
    $result['recovery'] = ['status' => $recovery->status(), 'ids' => array_map(static fn ($noticeView) => $noticeView->notice()->id(), $recovery->notices())];
    file_put_contents($directory.'/contention-result.json', json_encode($result, JSON_THROW_ON_ERROR));
} catch (Throwable $failure) {
    $primaryFailure = $failure;
    $observePhase('failure_observed');
    $failureEvidence = [
        'failure' => $failure::class, 'message' => $failure->getMessage(), 'phases' => $phaseObservations,
        'registration_started_at' => $registrationStartedAt ?? null, 'original' => $original ?? null,
        'resume_at' => $resumeAt ?? null, 'started' => $started ?? null, 'returned' => $returned ?? null
    ];
    try {
        $failureEvidence += [
            'worker_started_at' => $lockWorker->getStartTime(), 'worker_timeout' => $lockWorker->getTimeout(),
            'worker_exit' => $lockWorker->getExitCode(), 'worker_output' => $lockWorker->getOutput(),
            'worker_error' => $lockWorker->getErrorOutput(), 'calls' => $observedSubscriptionStore->calls ?? [],
            'capacity_locks' => $subscriptionConnection->capacityLocks
        ];
    } catch (Throwable $observationFailure) { $failureEvidence['observation_failure'] = $observationFailure::class; }
    throw $failure;
} finally {
    // Attempt every owned cleanup even when an earlier operation fails. Never replace the primary failure.
    $cleanupFailure = null;
    $cleanupErrors = [];
    foreach (['worker_stop' => static fn () => $lockWorker->stop(1), 'body_close' => static fn () => $body?->close(), 'connection_close' => static fn () => $subscriptionConnection->close()] as $operation => $cleanup) {
        try { $cleanup(); } catch (Throwable $failure) { $cleanupFailure ??= $failure; $cleanupErrors[$operation] = $failure::class; }
    }
    if (isset($failureEvidence)) {
        try {
            $failureEvidence['cleanup'] = ['at' => microtime(true), 'worker_running' => $lockWorker->isRunning(), 'worker_exit' => $lockWorker->getExitCode(),
                'worker_output' => $lockWorker->getOutput(), 'worker_error' => $lockWorker->getErrorOutput()];
        } catch (Throwable $observationFailure) { $failureEvidence['observation_failure'] = $observationFailure::class; }
    }
    if (isset($failureEvidence)) {
        $failureEvidence['cleanup_errors'] = $cleanupErrors;
        foreach (['worker-ready', 'worker-phases', 'lock-ready', 'locked-state', 'lock-released'] as $observation) {
            $failureEvidence[$observation] = ContentionObservations::read($directory.'/'.$observation.'.json');
        }
        ContentionObservations::write($directory.'/contention-failure.json', $failureEvidence);
    }
    if ($primaryFailure === null && $cleanupFailure !== null) { throw $cleanupFailure; }
}
