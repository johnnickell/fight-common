<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Error;

use Throwable;

/**
 * Interface SafeHttpProjector
 *
 * Trusted consumer policy may select public-only presentation and protocol metadata.
 * The exception and its cause are private; never return arbitrary diagnostic fields.
 */
interface SafeHttpProjector
{
    /**
     * Returns a deliberately public presentation, or null for fixed safe defaults
     */
    public function project(Throwable $failure, int $status): ?SafeHttpPresentation;
}
