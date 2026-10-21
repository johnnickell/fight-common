<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution\Value;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class ObservationFields
 *
 * @internal
 */
final readonly class ObservationFields
{
    /**
     * Validates an exact structured field set
     *
     * @param array<string, mixed> $data
     *
     * @phpstan-param list<string> $fields
     */
    public static function structure(array $data, array $fields): void
    {
        $keys = array_keys($data);
        sort($keys);
        sort($fields);
        if ($keys !== $fields) {
            throw new DomainException('Invalid observation structure.');
        }
    }

    /**
     * Returns a bounded opaque reference
     */
    public static function reference(mixed $value): string
    {
        if (!is_string($value) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/D', $value) !== 1) {
            throw new DomainException('Invalid observation reference.');
        }

        return $value;
    }
}
