<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Persistence\Subscription\Dbal;

use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use InvalidArgumentException;

/**
 * Class DbalSubscriptionTables
 *
 * Explicit shared schema/runtime selection without database effects
 */
final readonly class DbalSubscriptionTables
{
    private DbalTableName $stateTable;
    private DbalTableName $subscriptionTable;

    /**
     * Constructs DbalSubscriptionTables
     */
    public function __construct(
        string $stateTable = 'fight_mcp_subscription_state',
        string $subscriptionTable = 'fight_mcp_subscriptions'
    ) {
        $this->stateTable = new DbalTableName($stateTable);
        $this->subscriptionTable = new DbalTableName($subscriptionTable);
        if (strtolower($stateTable) === strtolower($subscriptionTable)) {
            throw new InvalidArgumentException('Subscription tables must have distinct names.');
        }
    }

    /**
     * Returns the selected schema state and capacity serialization table
     */
    public function stateTable(): DbalTableName
    {
        return $this->stateTable;
    }

    /**
     * Returns the selected technical subscription registration table
     */
    public function subscriptionTable(): DbalTableName
    {
        return $this->subscriptionTable;
    }
}
