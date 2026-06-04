<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Auth;

use DateTimeImmutable;
use Fight\Common\Adapter\Auth\Hmac\HmacKeyGenerator;

/**
 * Class Nonce
 */
readonly class Nonce
{
    /**
     * Constructs Nonce
     */
    public function __construct(
        private string $value,
        private DateTimeImmutable $expiresAt
    ) {
    }

    /**
     * Generates a new nonce with a TTL
     */
    public static function generate(int $bytes = 8, int $ttlSeconds = 300): static
    {
        $value = HmacKeyGenerator::generateSecureRandom($bytes);
        $expiresAt = new DateTimeImmutable(sprintf('+%d seconds', $ttlSeconds));

        /** @phpstan-ignore new.static */
        return new static($value, $expiresAt);
    }

    /**
     * Retrieves the nonce value
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Retrieves the expiry time
     */
    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * Checks if the nonce has expired
     */
    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }
}
