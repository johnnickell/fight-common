<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution\Usage;

/**
 * Enum UsageCompleteness
 */
enum UsageCompleteness: string
{
    case COMPLETE = 'complete';
    case PARTIAL = 'partial';
    case UNKNOWN = 'unknown';
    case UNAVAILABLE = 'unavailable';
}
