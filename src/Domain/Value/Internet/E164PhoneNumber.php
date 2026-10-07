<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\Internet;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class E164PhoneNumber
 */
final readonly class E164PhoneNumber extends ValueObject
{
    /**
     * Constructs E164PhoneNumber
     *
     * @throws DomainException When the value is not a lexical E.164 number
     */
    private function __construct(private string $value)
    {
        if (preg_match('/\A\+[1-9][0-9]{0,14}\z/', $this->value) !== 1) {
            throw new DomainException('Invalid E.164 phone number');
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
     * @inheritDoc
     */
    public function toString(): string
    {
        return $this->value;
    }
}
