<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\Publication;

/**
 * Enum NoticePublicationStatus
 *
 * Safe attempt outcomes, never delivery or processing receipts
 */
enum NoticePublicationStatus: string
{
    case PUBLISHED = 'published';
    case NOT_AVAILABLE = 'not_available';
    case DENIED = 'denied';
    case UNSUPPORTED = 'unsupported';
    case INVALID = 'invalid';
    case FAILED = 'failed';
}
