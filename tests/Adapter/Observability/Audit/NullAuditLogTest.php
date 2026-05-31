<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Observability\Audit;

use Fight\Common\Adapter\Observability\Audit\NullAuditLog;
use Fight\Common\Domain\Observability\AuditEntry;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NullAuditLog::class)]
class NullAuditLogTest extends UnitTestCase
{
    public function test_that_record_is_a_no_op(): void
    {
        $log = new NullAuditLog();
        $log->record(AuditEntry::record('actor', 'action'));
        $this->addToAssertionCount(1);
    }
}
