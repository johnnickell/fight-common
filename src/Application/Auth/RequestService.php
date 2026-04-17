<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth;

use Fight\Common\Application\Auth\Exception\CredentialsException;
use Psr\Http\Message\RequestInterface;

/**
 * Interface RequestService
 */
interface RequestService
{
    /**
     * Signs a request with authentication credentials
     *
     * @throws CredentialsException When an error signing credentials occurs
     */
    public function signRequest(RequestInterface $request): RequestInterface;
}
