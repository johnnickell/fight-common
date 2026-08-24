<?php

declare(strict_types=1);

namespace Fight\Common\Application\Http\JSend;

/**
 * Enum JSendStatus
 */
enum JSendStatus: string
{
    case SUCCESS = 'success';
    case FAIL = 'fail';
    case ERROR = 'error';
}
