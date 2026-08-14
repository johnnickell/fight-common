<?php

declare(strict_types=1);

use Prototype\AsyncPostCommit\CodeIgniterQueuePayload;
use Prototype\AsyncPostCommit\LaravelDatabaseQueue;
use Prototype\AsyncPostCommit\PostCommitProducer;
use Prototype\AsyncPostCommit\PrototypeQueue;
use Prototype\AsyncPostCommit\PrototypeWorker;
use Prototype\AsyncPostCommit\RecordingPrivatePublisher;
use Prototype\AsyncPostCommit\SymfonyMessengerQueue;
use Prototype\AsyncPostCommit\UserDeletedRealtimeSubscriber;
use Prototype\AsyncPostCommit\YiiForwardCompatibleQueue;

use function Prototype\AsyncPostCommit\expect;
use function Prototype\AsyncPostCommit\lockedVersions;
use function Prototype\AsyncPostCommit\writeReceipt;

require __DIR__ . '/vendor/autoload.php';

/** @param array<string, string> $packages */
function runLane(string $framework, PrototypeQueue $queue, array $packages): void
{
    $rolledBack = (new PostCommitProducer($queue))->delete(fail: true);
    expect(!$rolledBack->committed && !$rolledBack->deleted && !$rolledBack->auditWritten, "$framework rollback probe failed.");
    expect($queue->pending() === 0, "$framework queued work before a successful commit.");

    $mutation = (new PostCommitProducer($queue))->delete();
    expect($mutation->committed && $mutation->deleted && $mutation->auditWritten, "$framework atomic mutation probe failed.");
    expect($queue->pending() === 1, "$framework must retain one post-commit job before worker execution.");

    $publisher = new RecordingPrivatePublisher(failFirstAttempt: true);
    $subscriber = new UserDeletedRealtimeSubscriber($publisher);
    $worker = new PrototypeWorker($queue, $subscriber);

    try {
        $worker->runOne();
        throw new RuntimeException("$framework first worker attempt unexpectedly succeeded.");
    } catch (RuntimeException $exception) {
        expect($exception->getMessage() === 'prototype transport unavailable', "$framework worker failure mismatch.");
    }
    expect($queue->pending() === 1, "$framework must retain failed work for retry.");
    expect($publisher->published === [], "$framework failed attempt must not record a successful publish.");

    $asyncEnvelope = $worker->runOne();
    expect($asyncEnvelope !== null, "$framework retry did not produce an envelope.");
    expect($queue->pending() === 0, "$framework did not acknowledge successful work.");
    expect($publisher->attempts === 2 && count($publisher->published) === 1, "$framework retry accounting mismatch.");

    $syncPublisher = new RecordingPrivatePublisher();
    $syncSubscriber = new UserDeletedRealtimeSubscriber($syncPublisher);
    $syncEvent = (new Prototype\AsyncPostCommit\Mutation())->transact();
    expect($syncEvent !== null, "$framework synchronous event setup failed.");
    $syncEnvelope = $syncSubscriber->receive($syncEvent);
    expect($syncEnvelope === $asyncEnvelope, "$framework sync and async subscriber outcomes diverged.");

    writeReceipt($framework, [
        'prototype' => 'WF-017 asynchronous post-commit publication',
        'framework' => $framework,
        'packages' => $packages,
        'transport' => $queue->transport(),
        'rollback_enqueued' => false,
        'queued_after_commit' => true,
        'subscriber_class' => UserDeletedRealtimeSubscriber::class,
        'same_sync_async_outcome' => true,
        'worker_attempts' => 2,
        'successful_publications' => 1,
        'failed_work_retried' => true,
        'public_envelope' => $asyncEnvelope,
        'result' => 'passed',
    ]);
}

$versions = lockedVersions();
runLane('symfony', new SymfonyMessengerQueue('symfony'), [
    'symfony/messenger' => $versions['symfony/messenger'],
]);
runLane('laravel', new LaravelDatabaseQueue(), [
    'illuminate/queue' => $versions['illuminate/queue'],
]);
runLane('yii', new YiiForwardCompatibleQueue(), [
    'yiisoft/queue' => 'no stable release; intentionally not installed',
]);
runLane('codeigniter', new CodeIgniterQueuePayload(), [
    'codeigniter4/framework' => $versions['codeigniter4/framework'],
    'codeigniter4/queue' => $versions['codeigniter4/queue'],
]);
runLane('slim', new SymfonyMessengerQueue('slim'), [
    'symfony/messenger' => $versions['symfony/messenger'],
]);

fwrite(STDOUT, "WF-017 asynchronous post-commit prototype passed for Symfony, Laravel, Yii, CodeIgniter, and Slim.\n");
