<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Value;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class ObservationReference
 *
 * Exact opaque instrumentation reference, not a Notice reference or credential
 */
final readonly class ObservationReference extends ValueObject
{
    /**
     * Constructs ObservationReference
     */
    private function __construct(private string $value)
    {
    }

    /**
     * Creates a bounded opaque instrumentation reference without normalization
     */
    public static function fromString(mixed $value): self
    {
        if (!is_string($value) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/D', $value) !== 1) {
            throw new DomainException('Invalid observation reference.');
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
