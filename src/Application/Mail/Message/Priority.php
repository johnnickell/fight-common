<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mail\Message;

/**
 * Enum Priority
 */
enum Priority: int
{
    case HIGHEST = 1;
    case HIGH = 2;
    case NORMAL = 3;
    case LOW = 4;
    case LOWEST = 5;
}
