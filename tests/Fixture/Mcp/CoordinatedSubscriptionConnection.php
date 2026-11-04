<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Closure;
use Doctrine\DBAL\Connection;

/**
 * Class CoordinatedSubscriptionConnection
 */
final class CoordinatedSubscriptionConnection extends Connection
{
    public ?Closure $beforeTransaction = null;

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
}
