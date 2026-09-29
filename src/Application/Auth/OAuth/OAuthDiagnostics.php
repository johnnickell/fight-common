<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth\OAuth;

use Throwable;

/**
 * Interface OAuthDiagnostics
 */
interface OAuthDiagnostics
{
    /**
     * Records one safe classification and optional unexpected failure through redaction-aware diagnostics
     *
     * Redact messages, traces, arguments, previous exceptions and context before persistence in every
     * environment. Never persist credentials or sensitive claims. Do not throw or duplicate endpoint logs.
     */
    public function record(OAuthFailure $classification, ?Throwable $failure): void;
}
