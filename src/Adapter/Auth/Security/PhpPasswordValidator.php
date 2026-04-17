<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Auth\Security;

use Fight\Common\Application\Auth\Security\PasswordValidator;

/**
 * Class PhpPasswordValidator
 */
final readonly class PhpPasswordValidator implements PasswordValidator
{
    /**
     * Constructs PhpPasswordValidator
     */
    public function __construct(private string $algorithm, private ?array $options = null)
    {
    }

    /**
     * @inheritDoc
     */
    public function validate(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * @inheritDoc
     */
    public function needsRehash(string $hash): bool
    {
        if ($this->options === null) {
            return password_needs_rehash($hash, $this->algorithm);
        }

        return password_needs_rehash($hash, $this->algorithm, $this->options);
    }
}
