<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Value;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class ObservationName
 *
 * Consumer-owned namespaced activity kind or usage metric
 */
final readonly class ObservationName extends ValueObject
{
    /**
     * Constructs ObservationName
     */
    private function __construct(private string $value)
    {
    }

    /**
     * Creates an exact bounded namespaced instrumentation name
     */
    public static function fromString(mixed $value): self
    {
        if (
            !is_string($value) || strlen($value) > 96
            || preg_match('/\A[a-z][a-z0-9.-]*\/[a-z][a-z0-9._-]*\z/D', $value) !== 1
        ) {
            throw new DomainException('Invalid observation name.');
        }

        return new self($value);
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return $this->value;
    }
}
