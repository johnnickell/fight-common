<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

/**
 * Enum BaselineTagResolutionStatus
 */
enum BaselineTagResolutionStatus: string
{
    case RESOLVED = 'resolved';
    case MISSING = 'missing';
    case AMBIGUOUS = 'ambiguous';
    case DUPLICATE_NORMALIZED = 'duplicate_normalized';
    case NON_ANCESTOR = 'non_ancestor';
    case MOVING = 'moving';
}
