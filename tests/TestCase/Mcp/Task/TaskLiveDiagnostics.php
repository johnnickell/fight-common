<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp\Task;

use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\Mcp\Subscription\DbalSubscriptionStore;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionStore;
use Throwable;

/**
 * Class TaskLiveDiagnostics
 *
 * Keeps bounded private fixture evidence in memory until request shutdown, without heartbeat I/O or row scans
 */
final class TaskLiveDiagnostics implements McpSubscriptionStore
{
    public array $events = [];
    public array $heartbeats = ['count' => 0];
    private readonly McpSubscriptionStore $subscriptionStore;

    public function __construct(private readonly Connection $connection)
    {
        $this->subscriptionStore = new DbalSubscriptionStore($connection);
    }

    public static function clock(): array
    {
        return ['wall' => microtime(true), 'monotonic' => hrtime(true)];
    }

    public static function chain(Throwable $failure): array
    {
        $chain = [];
        do {
            $chain[] = ['class' => $failure::class, 'message' => $failure->getMessage(), 'code' => $failure->getCode(), 'file' => $failure->getFile(), 'line' => $failure->getLine()];
            $failure = $failure->getPrevious();
        } while ($failure !== null && count($chain) < 8);
        return $chain;
    }

    public static function rows(Connection $connection): array
    {
        $rows = ['sampleStarted' => self::clock(), 'coherentSnapshot' => false];
        // Separate bounded live reads, not an invented atomic snapshot or per-heartbeat database work.
        foreach (['fight_mcp_subscriptions', 'fight_tasks'] as $table) {
            try { $rows[$table] = $connection->fetchAllAssociative('SELECT * FROM '.$table.' LIMIT 16'); }
            catch (Throwable $failure) { $rows[$table] = ['unavailable' => self::chain($failure)]; }
        }
        $rows['sampleFinished'] = self::clock();
        return $rows;
    }

    public function event(string $boundary, array $context = []): void
    {
        if (count($this->events) === 64) { array_shift($this->events); }
        $this->events[] = ['boundary' => $boundary, ...self::clock(), ...$context];
    }

    public function open(string $id, string $owner, array $context, int $now, int $expires): void
    {
        $this->event('open-enter', ['registration' => $id, 'now' => $now, 'expires' => $expires]);
        try { $this->subscriptionStore->open($id, $owner, $context, $now, $expires); }
        catch (Throwable $failure) { $this->event('open-error', ['chain' => self::chain($failure)]); throw $failure; }
        $this->event('open-return');
    }

    public function heartbeat(string $id, string $owner, int $now, int $expires): void
    {
        $sample = ['registration' => $id, 'now' => $now, 'expires' => $expires, 'enter' => self::clock()];
        ++$this->heartbeats['count'];
        try { $this->subscriptionStore->heartbeat($id, $owner, $now, $expires); $sample['result'] = 'returned'; }
        catch (Throwable $failure) { $sample['chain'] = self::chain($failure); throw $failure; }
        finally {
            $sample['leave'] = self::clock();
            $this->heartbeats['first'] ??= $sample;
            $this->heartbeats['last'] = $sample;
            if (isset($sample['chain']) || $sample['leave']['monotonic'] - $sample['enter']['monotonic'] >= 100000000) {
                $this->event('heartbeat-slow-or-error', $sample);
            }
        }
    }

    public function close(string $id, string $owner, int $now): void
    {
        $this->event('close-enter', ['registration' => $id, 'now' => $now]);
        try { $this->subscriptionStore->close($id, $owner, $now); }
        catch (Throwable $failure) { $this->event('close-error', ['chain' => self::chain($failure)]); throw $failure; }
        $this->event('close-return');
    }

    public function cleanup(int $now, int $limit): int
    {
        return $this->subscriptionStore->cleanup($now, $limit);
    }
}
