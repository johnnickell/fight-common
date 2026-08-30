<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Auth\Security\Laravel;

use Fight\Common\Application\Auth\Security\PasswordValidator;
use Illuminate\Contracts\Hashing\Hasher;

/**
 * Class LaravelPasswordValidator
 */
final readonly class LaravelPasswordValidator implements PasswordValidator
{
    /**
     * Constructs LaravelPasswordValidator
     */
    public function __construct(private Hasher $hasher)
    {
    }

    /**
     * @inheritDoc
     */
    public function validate(string $password, string $hash): bool
    {
        return $this->hasher->check($password, $hash);
    }

    /**
     * @inheritDoc
     */
    public function needsRehash(string $hash): bool
    {
        return $this->hasher->needsRehash($hash);
    }
}
