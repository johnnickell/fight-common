<?php

declare(strict_types=1);

namespace Fight\Common\Application;

use Cron\CronExpression;
use DateTimeImmutable;
use Fight\Common\Domain\DomainDependency;
use Psr\Log\LoggerInterface;

final readonly class ApplicationDependency
{
    public function __construct(
        private DomainDependency $domain,
        private DateTimeImmutable $createdAt,
        private LoggerInterface $logger,
        private CronExpression $cronExpression,
    ) {}
}
