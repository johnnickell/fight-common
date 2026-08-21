<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release\Boundary;

/**
 * Class Sha256Digest
 *
 * Owns one exact lowercase hexadecimal SHA-256 value.
 */
final readonly class Sha256Digest
{
    /**
     * Constructs Sha256Digest
     */
    private function __construct(public string $value)
    {
    }

    /**
     * Returns a validated digest or null for malformed boundary data
     */
    public static function tryFrom(string $value): ?self
    {
        if (preg_match('/\A[0-9a-f]{64}\z/D', $value) !== 1) {
            return null;
        }

        return new self($value);
    }
}
