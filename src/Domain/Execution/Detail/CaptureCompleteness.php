<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution\Detail;

/**
 * Enum CaptureCompleteness
 *
 * Describes supplied capture, not current storage availability
 */
enum CaptureCompleteness: string
{
    case COMPLETE = 'complete';
    case PARTIAL = 'partial';
    case ABSENT = 'absent';
    case UNAVAILABLE = 'unavailable';
    case EXPIRED = 'expired';
}
