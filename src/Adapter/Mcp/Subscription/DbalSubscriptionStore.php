<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Subscription;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptionStore;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Value\Basic\StrictJson;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Class DbalSubscriptionStore
 */
final class DbalSubscriptionStore implements McpSubscriptionStore
{
    private bool $failed = false;

    /**
     * Constructs DbalSubscriptionStore
     */
    public function __construct(private readonly Connection $connection, private readonly int $maxActive = 256)
    {
        $platform = $connection->getDatabasePlatform();
        if (
            (!$platform instanceof SQLitePlatform && !$platform instanceof AbstractMySQLPlatform
            && !$platform instanceof PostgreSQLPlatform) || $maxActive < 1 || $maxActive > 256
        ) {
            throw new LogicException('Invalid subscription storage configuration.');
        }
    }

    /**
     * @inheritDoc
     */
    public function open(string $id, string $owner, array $context, int $now, int $expires): void
    {
        $this->identity($id, $owner, $now);
        $this->expiry($now, $expires);
        NoticeSelection::structure($context, ['subscriber', 'selection', 'requested', 'accepted']);
        Notice::reference($context['subscriber']);
        $payload = json_encode(StrictJson::fromObject($context), JSON_THROW_ON_ERROR);
        if (strlen($payload) > 65536) {
            throw new RuntimeException('Subscription context exceeds bounds.');
        }

        $this->transaction(function () use ($id, $owner, $payload, $now, $expires): void {
            $active = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM fight_mcp_subscriptions WHERE closed_at = 0 AND expires_at > ?',
                [$now]
            );
            $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM fight_mcp_subscriptions');
            if ($active >= $this->maxActive || $total >= 4096) {
                throw new RuntimeException('Subscription capacity exhausted.');
            }

            $this->connection->insert('fight_mcp_subscriptions', [
                'registration_id' => $id,
                'owner'           => $owner,
                'context'         => $payload,
                'opened_at'       => $now,
                'observed_at'     => $now,
                'expires_at'      => $expires,
                'closed_at'       => 0
            ]);
        });
    }

    /**
     * @inheritDoc
     */
    public function heartbeat(string $id, string $owner, int $now, int $expires): void
    {
        $startedAt = time();
        $this->identity($id, $owner, $now);
        $this->expiry($now, $expires);
        $this->transaction(function () use ($id, $owner, $now, $expires, $startedAt): void {
            // The supplied clock is as of invocation, not acquisition of the serialized write boundary.
            $now += max(0, time() - $startedAt);
            if ($expires <= $now) {
                throw new RuntimeException('Subscription freshness elapsed while awaiting renewal.');
            }

            $changed = $this->connection->executeStatement(
                <<<'SQL'
                UPDATE fight_mcp_subscriptions SET observed_at = ?, expires_at = ?
                WHERE registration_id = ? AND owner = ? AND closed_at = 0 AND observed_at <= ? AND expires_at > ?
                SQL,
                [$now, $expires, $id, $owner, $now, $now]
            );
            // MySQL may report zero for a same-second no-op; independently verify the owned fresh state.
            if (
                $changed === 0 && !$this->connection->fetchOne(
                    <<<'SQL'
                    SELECT registration_id FROM fight_mcp_subscriptions WHERE registration_id = ? AND owner = ?
                    AND closed_at = 0 AND observed_at = ? AND expires_at = ? AND expires_at > ?
                    SQL,
                    [$id, $owner, $now, $expires, $now]
                )
            ) {
                throw new RuntimeException('Subscription ownership or freshness lost.');
            }
        });
    }

    /**
     * @inheritDoc
     */
    public function close(string $id, string $owner, int $now): void
    {
        $this->identity($id, $owner, $now);
        $this->transaction(function () use ($id, $owner, $now): void {
            $this->connection->executeStatement(
                <<<'SQL'
                UPDATE fight_mcp_subscriptions SET closed_at = ?, observed_at = ?
                WHERE registration_id = ? AND owner = ? AND closed_at = 0 AND observed_at <= ?
                SQL,
                [$now, $now, $id, $owner, $now]
            );
        });
    }

    /**
     * @inheritDoc
     */
    public function cleanup(int $now, int $limit): int
    {
        if ($now < 1 || $now > 2147483647 || $limit < 1 || $limit > 256) {
            throw new LogicException('Invalid subscription cleanup bounds.');
        }

        return $this->transaction(function () use ($now, $limit): int {
            $sql = <<<'SQL'
                SELECT registration_id FROM fight_mcp_subscriptions WHERE expires_at <= ? OR closed_at > 0
                ORDER BY expires_at, registration_id
                SQL;
            $rows = $this->connection->fetchFirstColumn($sql.' LIMIT '.$limit, [$now]);
            foreach ($rows as $id) {
                $this->connection->delete('fight_mcp_subscriptions', ['registration_id' => $id]);
            }

            return count($rows);
        });
    }

    /**
     * Validates finite opaque execution identity separately from protocol correlation
     */
    private function identity(string $id, string $owner, int $now): void
    {
        if (
            preg_match('/\A[0-9a-f]{32}\z/D', $id) !== 1 || preg_match('/\A[0-9a-f]{32}\z/D', $owner) !== 1
            || $now < 1 || $now > 2147483647
        ) {
            throw new LogicException('Invalid subscription identity or clock.');
        }
    }

    /**
     * Validates a short observation lease, never a permission lease
     */
    private function expiry(int $now, int $expires): void
    {
        if ($expires <= $now || $expires > $now + 5 || $expires > 2147483647) {
            throw new LogicException('Invalid subscription freshness interval.');
        }
    }

    /**
     * Executes a short owner transaction under the capacity lock without touching caller work
     *
     * @template T
     *
     * @phpstan-param callable(): T $operation
     * @phpstan-return T
     */
    private function transaction(callable $operation): mixed
    {
        if ($this->failed || $this->connection->isTransactionActive()) {
            throw new LogicException('Subscription tracking requires its own idle connection.');
        }

        try {
            $this->connection->beginTransaction();
            $this->connection->executeStatement(
                'UPDATE fight_mcp_subscription_state SET version = version WHERE singleton = 1'
            );
            if (
                (int) $this->connection->fetchOne(
                    'SELECT version FROM fight_mcp_subscription_state WHERE singleton = 1'
                ) !== 1
            ) {
                throw new RuntimeException('Unsupported subscription schema.');
            }

            $result = $operation();
            $this->connection->commit();

            return $result;
        } catch (Throwable $throwable) {
            $this->failed = true;
            if ($this->connection->isTransactionActive()) {
                try {
                    $this->connection->rollBack();
                } catch (Throwable) {
                    // Storage remains failed; uncertain writes are not claimed absent or healthy.
                }
            }

            throw $throwable;
        }
    }
}
