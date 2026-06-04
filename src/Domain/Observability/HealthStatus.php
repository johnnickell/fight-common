<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Observability;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class HealthStatus
 */
final readonly class HealthStatus extends ValueObject
{
    private const string HEALTHY = 'healthy';

    private const string DEGRADED = 'degraded';

    private const string UNHEALTHY = 'unhealthy';

    private const array SEVERITY = [
        self::HEALTHY => 0,
        self::DEGRADED => 1,
        self::UNHEALTHY => 2,
    ];

    /**
     * @throws DomainException When the status is invalid
     */
    private function __construct(private string $value)
    {
        if (!array_key_exists($this->value, self::SEVERITY)) {
            throw new DomainException(sprintf('Invalid health status: %s', $this->value));
        }
    }

    /**
     * @inheritDoc
     */
    public static function fromString(string $value): static
    {
        return new static($value);
    }

    /**
     * Creates a healthy status
     */
    public static function healthy(): static
    {
        return new static(self::HEALTHY);
    }

    /**
     * Creates a degraded status
     */
    public static function degraded(): static
    {
        return new static(self::DEGRADED);
    }

    /**
     * Creates an unhealthy status
     */
    public static function unhealthy(): static
    {
        return new static(self::UNHEALTHY);
    }

    /**
     * Returns the more severe of two statuses
     */
    public function worst(HealthStatus $other): static
    {
        return self::SEVERITY[$this->value] >= self::SEVERITY[$other->value] ? $this : $other;
    }

    /**
     * Checks if the status is healthy
     */
    public function isHealthy(): bool
    {
        return $this->value === self::HEALTHY;
    }

    /**
     * Checks if the status is degraded
     */
    public function isDegraded(): bool
    {
        return $this->value === self::DEGRADED;
    }

    /**
     * Checks if the status is unhealthy
     */
    public function isUnhealthy(): bool
    {
        return $this->value === self::UNHEALTHY;
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return $this->value;
    }
}
