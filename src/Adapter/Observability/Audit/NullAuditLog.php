<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Observability\Audit;

use Fight\Common\Application\Observability\AuditLog;
use Fight\Common\Domain\Observability\AuditEntry;

/**
 * Class NullAuditLog
 */
final class NullAuditLog implements AuditLog
{
    /**
     * @inheritDoc
     */
    public function record(AuditEntry $entry): void
    {
    }
}
