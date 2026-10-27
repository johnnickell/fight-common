<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\Publication;

use Throwable;

/**
 * Interface NoticePublicationDiagnostics
 */
interface NoticePublicationDiagnostics
{
    /**
     * Records private failures through consumer redaction without exposing raw diagnostics to clients
     *
     * Exceptions may contain credentials or protected data; this is not public output
     */
    public function record(Throwable $failure): void;
}
