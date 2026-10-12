<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Error;

use Throwable;

/**
 * Interface SafeHttpDiagnostics
 *
 * The single trusted reporting owner for this composition. Redact messages,
 * causes, traces and context before passing anything to a logger/formatter.
 * Do not pass the Throwable as PSR-3 exception context. A failed sink cannot
 * be claimed delivered; the middleware will not report recursively.
 */
interface SafeHttpDiagnostics
{
    /**
     * Reports one handled failure through a redaction-aware trusted boundary
     */
    public function report(Throwable $failure): void;
}
