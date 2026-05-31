<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Observability\Health;

use Doctrine\DBAL\Connection;
use Fight\Common\Application\Observability\HealthCheck;
use Fight\Common\Domain\Observability\HealthResult;
use Fight\Common\Domain\Observability\HealthStatus;
use Throwable;

/**
 * Class DatabaseHealthCheck
 */
final readonly class DatabaseHealthCheck implements HealthCheck
{
    /**
     * Constructs DatabaseHealthCheck
     */
    public function __construct(
        private Connection $connection,
        private string $checkName = 'database'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return $this->checkName;
    }

    /**
     * @inheritDoc
     */
    public function check(): HealthResult
    {
        try {
            $start = hrtime(true);
            $this->connection->executeQuery($this->connection->getDatabasePlatform()->getDummySelectSQL());
            $elapsed = round((hrtime(true) - $start) / 1e6, 2);

            return new HealthResult(
                $this->checkName,
                HealthStatus::healthy(),
                sprintf('ping %sms', $elapsed)
            );
        } catch (Throwable $throwable) {
            return new HealthResult(
                $this->checkName,
                HealthStatus::unhealthy(),
                $throwable->getMessage()
            );
        }
    }
}
