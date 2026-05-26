<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Observability\Audit;

use Fight\Common\Adapter\Observability\Audit\LoggingAuditLog;
use Fight\Common\Domain\Observability\AuditEntry;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

#[CoversClass(LoggingAuditLog::class)]
class LoggingAuditLogTest extends UnitTestCase
{
    public function test_that_record_logs_at_configured_level(): void
    {
        $entry = AuditEntry::record('user:1', 'login', ['ip' => '127.0.0.1']);

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')
            ->once()
            ->withArgs(fn(string $level, string $message, array $context): bool => $level === LogLevel::INFO
                && $message === 'audit'
                && $context['actor'] === 'user:1'
                && $context['action'] === 'login');

        $log = new LoggingAuditLog($logger);
        $log->record($entry);
    }

    public function test_that_record_uses_custom_log_level(): void
    {
        $entry = AuditEntry::record('system', 'deploy');

        /** @var MockInterface|LoggerInterface $logger */
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('log')
            ->once()
            ->withArgs(fn(string $level): bool => $level === LogLevel::DEBUG);

        $log = new LoggingAuditLog($logger, LogLevel::DEBUG);
        $log->record($entry);
    }
}
