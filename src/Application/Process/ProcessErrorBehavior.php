<?php

declare(strict_types=1);

namespace Fight\Common\Application\Process;

/**
 * Enum ProcessErrorBehavior
 */
enum ProcessErrorBehavior: int
{
    case EXCEPTION = 1;
    case IGNORE    = 2;
    case RETRY     = 3;
}
