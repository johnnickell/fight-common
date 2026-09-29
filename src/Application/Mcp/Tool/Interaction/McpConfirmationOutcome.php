<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool\Interaction;

/**
 * Enum McpConfirmationOutcome
 */
enum McpConfirmationOutcome
{
    case CONSUMED;
    case ABSENT;
    case EXPIRED;
    case MISMATCHED;
    case ALREADY_CONSUMED;
    case CONTENDED;
}
