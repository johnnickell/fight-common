<?php

declare(strict_types=1);

namespace Fight\Common\Application\FileTransfer\Resource;

/**
 * Enum ResourceType
 */
enum ResourceType: string
{
    case FILE   = 'file';
    case DIR    = 'dir';
    case LINK   = 'link';
    case FIFO   = 'fifo';
    case CHAR   = 'char';
    case BLOCK  = 'block';
    case SOCKET = 'socket';
    case UNKNOWN = 'unknown';
}
