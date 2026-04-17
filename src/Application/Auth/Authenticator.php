<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth;

use Fight\Common\Application\Auth\Exception\AuthException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface Authenticator
 */
interface Authenticator
{
    /**
     * Validates a server request authentication
     *
     * @throws AuthException When an authentication error occurs
     */
    public function validate(ServerRequestInterface $request): bool;
}
