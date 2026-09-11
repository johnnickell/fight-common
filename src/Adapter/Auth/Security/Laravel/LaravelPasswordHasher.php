<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Auth\Security\Laravel;

use Fight\Common\Application\Auth\Exception\PasswordException;
use Fight\Common\Application\Auth\Security\PasswordHasher;
use Illuminate\Contracts\Hashing\Hasher;
use Throwable;

/**
 * Class LaravelPasswordHasher
 */
final readonly class LaravelPasswordHasher implements PasswordHasher
{
    /**
     * Constructs LaravelPasswordHasher
     */
    public function __construct(private Hasher $hasher)
    {
    }

    /**
     * @inheritDoc
     */
    public function hash(string $password): string
    {
        if (str_contains($password, chr(0))) {
            throw new PasswordException('Unexpected value received');
        }

        try {
            return $this->hasher->make($password);
        } catch (Throwable $throwable) {
            throw new PasswordException('Password hashing failed', previous: $throwable);
        }
    }
}
