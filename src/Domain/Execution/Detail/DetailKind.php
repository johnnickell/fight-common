<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution\Detail;

/**
 * Enum DetailKind
 */
enum DetailKind: string
{
    case MESSAGE = 'message';
    case TOOL_ARGUMENTS = 'tool_arguments';
    case TOOL_RESULT = 'tool_result';
    case PROCESS_OUTPUT = 'process_output';
}
