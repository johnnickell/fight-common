<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Observability\Audit;

use Fight\Common\Application\Observability\AuditLog;
use Fight\Common\Domain\Observability\AuditEntry;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class LoggingAuditLog
 */
final class LoggingAuditLog implements AuditLog
{
    /**
     * Constructs LoggingAuditLog
     */
    public function __construct(
        private LoggerInterface $logger,
        private string $logLevel = LogLevel::INFO
    ) {
    }

    /**
     * @inheritDoc
     */
    public function record(AuditEntry $entry): void
    {
        $this->logger->log($this->logLevel, 'audit', $entry->toArray());
    }
}
