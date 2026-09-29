<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth\OAuth;

/**
 * Enum OAuthFailure
 */
enum OAuthFailure
{
    case MISSING_CREDENTIALS;
    case INVALID_REQUEST;
    case INVALID_TOKEN;
    case INSUFFICIENT_SCOPE;
    case INTERNAL_ERROR;
}
