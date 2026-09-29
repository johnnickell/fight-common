<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth\OAuth;

use RuntimeException;

/**
 * Class OAuthTokenRejected
 */
final class OAuthTokenRejected extends RuntimeException
{
    /**
     * Constructs OAuthTokenRejected
     *
     * Deliberately carries no token, claims, underlying exception or validation detail.
     */
    public function __construct()
    {
        parent::__construct('OAuth token rejected.');
    }
}
