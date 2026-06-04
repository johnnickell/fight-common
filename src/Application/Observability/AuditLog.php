<?php

declare(strict_types=1);

namespace Fight\Common\Application\Observability;

use Fight\Common\Domain\Observability\AuditEntry;

/**
 * Interface AuditLog
 */
interface AuditLog
{
    /**
     * Records an audit entry
     */
    public function record(AuditEntry $entry): void;
}
