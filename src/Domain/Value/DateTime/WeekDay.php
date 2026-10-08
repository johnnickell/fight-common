<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\DateTime;

/**
 * Enum WeekDay
 */
enum WeekDay: int
{
    case SUNDAY = 0;
    case MONDAY = 1;
    case TUESDAY = 2;
    case WEDNESDAY = 3;
    case THURSDAY = 4;
    case FRIDAY = 5;
    case SATURDAY = 6;
}
