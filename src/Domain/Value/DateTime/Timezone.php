<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\DateTime;

use DateTimeZone;
use Exception;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\ValueObject;
use Override;

/**
 * Class Timezone
 */
final readonly class Timezone extends ValueObject
{
    /**
     * Constructs Timezone
     *
     * @throws DomainException When the timezone name is invalid
     */
    public function __construct(private string $timezone)
    {
        try {
            new DateTimeZone($timezone);
        } catch (Exception) {
            throw new DomainException(sprintf('Invalid timezone: %s', $timezone));
        }
    }

    /**
     * Creates instance from a string representation
     *
     * @throws DomainException When the timezone name is invalid
     */
    #[Override]
    public static function fromString(string $value): static
    {
        return new static($value);
    }

    /**
     * Retrieves the timezone name
     */
    public function value(): string
    {
        return $this->timezone;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function toString(): string
    {
        return $this->timezone;
    }
}
