<?php

declare(strict_types=1);

require dirname(__DIR__, 3).'/vendor/autoload.php';

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Fight\Common\Adapter\Mcp\Http\McpResponseEmitter;
use Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal\DbalSubscriptionStore;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionStore;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Mcp\Notice\Query\RecoverNotices;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\Mcp\SubscriptionFixture;
use Symfony\Component\Process\Process;

// Observe the real DBAL serialized boundary, without substituting SQL or the production store algorithm.
final class SubscriptionContentionConnection extends Connection
{
    public bool $observe = false;
    public string $operation = '';
    public array $capacityLocks = [];

    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        if (!$this->observe || $sql !== 'UPDATE fight_mcp_subscription_state SET version = version WHERE singleton = 1') {
            return parent::executeStatement($sql, $params, $types);
        }
        $event = ['operation' => $this->operation, 'entered_at' => microtime(true)];
        try {
            $result = parent::executeStatement($sql, $params, $types);
            $event['acquired_at'] = microtime(true);
            return $result;
        } catch (Throwable $failure) {
            $event['failure'] = $failure->getMessage();
            throw $failure;
        } finally {
            $event['returned_at'] = microtime(true);
            $this->capacityLocks[] = $event;
        }
    }
}

[$script, $directory, $mode, $transport] = $argv;
$params = json_decode(file_get_contents($directory.'/connection.json'), true, flags: JSON_THROW_ON_ERROR);
$params['wrapperClass'] = SubscriptionContentionConnection::class;
$db = DriverManager::getConnection($params);
if ($db->getParams()['driver'] === 'pdo_sqlite') { $db->executeStatement('PRAGMA busy_timeout = 7000'); }
if ($mode === 'timeout') {
    match ($db->getParams()['driver']) {
        'pdo_sqlite' => $db->executeStatement('PRAGMA busy_timeout = 1000'),
        'pdo_mysql' => $db->executeStatement('SET SESSION innodb_lock_wait_timeout = 1'),
        'pdo_pgsql' => $db->executeStatement("SET lock_timeout = '1s'")
    };
}
$tracking = new class(new DbalSubscriptionStore($db), $db, $directory) implements McpSubscriptionStore {
    public bool $armed = false;
    public array $operations = [];
    private bool $signalled = false;

    public function __construct(private McpSubscriptionStore $subscriptionStore, private SubscriptionContentionConnection $connection, private string $directory) {}
    public function open(string $id, string $owner, array $context, int $now, int $expires): void { $this->subscriptionStore->open($id, $owner, $context, $now, $expires); }
    public function heartbeat(string $id, string $owner, int $now, int $expires): void
    {
        $this->run('heartbeat', $now, $expires, fn () => $this->subscriptionStore->heartbeat($id, $owner, $now, $expires));
    }
    public function close(string $id, string $owner, int $now): void
    {
        $this->run('close', $now, null, fn () => $this->subscriptionStore->close($id, $owner, $now));
    }
    public function cleanup(int $now, int $limit): int { return $this->subscriptionStore->cleanup($now, $limit); }

    private function run(string $operation, int $now, ?int $expires, Closure $callback): void
    {
        if (!$this->armed) { $callback(); return; }
        $event = ['operation' => $operation, 'entered_at' => microtime(true), 'now' => $now, 'proposed_expires' => $expires];
        $this->connection->operation = $operation;
        if (!$this->signalled) {
            file_put_contents($this->directory.'/operation-entered.json', json_encode($event, JSON_THROW_ON_ERROR));
            $this->signalled = true;
        }
        try { $callback(); }
        catch (Throwable $failure) { $event['failure'] = $failure->getMessage(); throw $failure; }
        finally { $event['returned_at'] = microtime(true); $this->operations[] = $event; }
    }
};
$hold = $mode === 'overlong' ? 5200000 : 2200000;
$worker = new Process([PHP_BINARY, __DIR__.'/subscription-lock-worker.php', $directory, (string) $hold, $argv[4] ?? '0', 'synchronized']);
$worker->setTimeout(25);
$await = static function (string $file) use ($directory, $worker): array {
    $deadline = hrtime(true) + 10000000000;
    while (!file_exists($directory.'/'.$file)) {
        if (!$worker->isRunning() || hrtime(true) >= $deadline) {
            throw new RuntimeException('Fixture precondition missing '.$file.': '.$worker->getErrorOutput());
        }
        usleep(1000);
    }
    return json_decode(file_get_contents($directory.'/'.$file), true, flags: JSON_THROW_ON_ERROR);
};
$result = ['bootstrap_delay_us' => (int) ($argv[4] ?? 0)];
$body = null;
try {
    // Pay bootstrap/autoload/connection costs before opening the independent five-second freshness window.
    $worker->start();
    $result['worker_prepared'] = $await('worker-ready.json');
    $f = new SubscriptionFixture($db, lifetime: 20, subscriptionStore: $tracking);
    $result['opening_started'] = microtime(true);
    $response = $f->endpoint->handle(SubscriptionFixture::request(id: '7'));
    $body = $response->getBody();
    echo $body->read(65536);
    (new NoticeFixture($db))->record(['id' => 'retained', 'type' => 'mcp.tools.list_changed']);
    $original = $db->fetchAssociative('SELECT observed_at, expires_at, closed_at FROM fight_mcp_subscriptions');
    $result['original'] = $original;
    // Bound the successful emitter after its first batch; the subsequent source failure is an intentional stop.
    $reads = 0;
    $f->queryPolicy = static function () use (&$reads): void { if (++$reads > 1) { throw new RuntimeException('Fixture stop after first live batch'); } };
    touch($directory.'/lock-request');
    $result['worker_lock'] = $await('lock-ready.json');
    $remaining = $mode === 'fast' ? 3.5 : 0.8;
    $target = $mode === 'pre-expired' ? (int) $original['expires_at'] + 0.05 : (int) $original['expires_at'] - $remaining;
    $waitDeadline = hrtime(true) + 6000000000;
    while (microtime(true) < $target) {
        if (hrtime(true) >= $waitDeadline) { throw new RuntimeException('Fixture clock did not reach the bounded renewal target.'); }
        usleep(1000);
    }
    $result['started'] = microtime(true);
    if ($mode !== 'pre-expired' && $result['started'] >= (int) $original['expires_at']) {
        throw new RuntimeException('Fixture missed the pre-renewal freshness window before transport resume.');
    }
    $tracking->armed = true;
    $db->observe = true;
    $result['position_before'] = $body->tell();
    if ($transport === 'emitter') { (new McpResponseEmitter())->emitBody($response); }
    else { echo $body->read(65536); }
    $result['returned'] = microtime(true);
    $result['worker_exit'] = $worker->wait();
    $result['worker_error'] = $worker->getErrorOutput();
    $result['locked'] = $await('locked-state.json');
    $result['worker_release'] = $await('lock-released.json');
    $result['row'] = $db->fetchAssociative('SELECT observed_at, expires_at, closed_at FROM fight_mcp_subscriptions');
    $result['eof'] = $body->eof();
    $result['position_after'] = $body->tell();
    $result['diagnostics'] = $f->diagnostics;
    $result['transaction_active'] = $db->isTransactionActive();
    $result['retained'] = (int) $db->fetchOne('SELECT COUNT(*) FROM fight_notices');
    $body->close();
    $f->queryPolicy = null;
    $recovery = $f->queries->fetch(new RecoverNotices(NoticeSelection::create(['scope'])));
    $result['recovery'] = ['status' => $recovery->status(), 'ids' => array_map(static fn ($view) => $view->notice()->id(), $recovery->notices())];
} catch (Throwable $failure) {
    $result['fixture_failure'] = $failure->getMessage();
    throw $failure;
} finally {
    $worker->stop(1);
    $body?->close();
    $result['operations'] = $tracking->operations;
    $result['capacity_locks'] = $db->capacityLocks;
    file_put_contents($directory.'/contention-result.json', json_encode($result, JSON_THROW_ON_ERROR));
    $db->close();
}
