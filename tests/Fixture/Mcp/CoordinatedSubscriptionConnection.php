<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Closure;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Class CoordinatedSubscriptionConnection
 */
final class CoordinatedSubscriptionConnection extends Connection
{
    public ?Closure $beforeTransaction = null;
    public ?string $operation = null;
    public array $capacityLocks = [];

    /**
     * Records entry inside the real store operation before its capacity lock
     */
    public function beginTransaction(): void
    {
        if ($this->beforeTransaction !== null) {
            ($this->beforeTransaction)();
        }
        parent::beginTransaction();
    }

    /**
     * Records actual capacity lock acquisition or failure without substituting the SQL operation
     */
    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        if ($this->operation === null || $sql !== 'UPDATE fight_mcp_subscription_state SET version = version WHERE singleton = 1') {
            return parent::executeStatement($sql, $params, $types);
        }
        $lockEvent = ['operation' => $this->operation, 'entered_at' => microtime(true)];
        try {
            $affectedRows = parent::executeStatement($sql, $params, $types);
            $lockEvent['acquired_at'] = microtime(true);
            return $affectedRows;
        } catch (Throwable $failure) {
            $lockEvent['failure'] = $failure::class;
            throw $failure;
        } finally {
            $lockEvent['returned_at'] = microtime(true);
            $this->capacityLocks[] = $lockEvent;
        }
    }
}
