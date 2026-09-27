<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use Throwable;

/**
 * Interface McpDiagnostics
 */
interface McpDiagnostics
{
    /**
     * Records one unexpected failure through the consumer's redaction-aware diagnostics
     *
     * Implementations must redact secrets before persistence and must not throw.
     * The failure is never included in the public response.
     */
    public function record(Throwable $failure): void;
}
