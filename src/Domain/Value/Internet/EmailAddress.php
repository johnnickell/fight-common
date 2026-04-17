<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\Internet;

use Fight\Common\Domain\Value\ValueObject;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class EmailAddress
 */
final readonly class EmailAddress extends ValueObject
{
    /**
     * Constructs EmailAddress
     *
     * @throws DomainException When the email address is invalid
     */
    private function __construct(private string $value)
    {
        if (!Validate::isEmail($this->value)) {
            $message = sprintf('Invalid email address: %s', $this->value);
            throw new DomainException($message);
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
     * Retrieves the local part
     *
     * @return string
     */
    public function localPart(): string
    {
        $parts = explode('@', $this->value);

        return $parts[0];
    }

    /**
     * Retrieves the domain part
     *
     * @return string
     */
    public function domainPart(): string
    {
        $parts = explode('@', $this->value);
        $domain = trim($parts[1], '[]');

        return $domain;
    }

    /**
     * Retrieves the unique lowercase value
     *
     * @return string
     */
    public function canonical(): string
    {
        return strtolower($this->value);
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return $this->value;
    }
}
