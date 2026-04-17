<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Messaging;

/**
 * Enum MessageType
 */
enum MessageType: string
{
    case COMMAND = 'command';
    case QUERY = 'query';
    case EVENT = 'event';
}
