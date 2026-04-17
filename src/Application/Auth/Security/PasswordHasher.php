<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth\Security;

use Fight\Common\Application\Auth\Exception\PasswordException;

/**
 * Interface PasswordHasher
 */
interface PasswordHasher
{
    /**
     * Hashes a password
     * 
     * @throws PasswordException When password hashing fails
     */
    public function hash(string $password): string;
}
