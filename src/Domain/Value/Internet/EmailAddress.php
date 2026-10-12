<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\Internet;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Utility\Validate;
use Fight\Common\Domain\Value\ValueObject;

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
     * Returns the local part with original quotes and escape bytes
     */
    public function localPart(): string
    {
        // Construction validation ensures only the local part can contain additional at-signs.
        $separator = (int) strrpos($this->value, '@');

        return substr($this->value, 0, $separator);
    }

    /**
     * Returns the domain part without literal brackets
     */
    public function domainPart(): string
    {
        $separator = (int) strrpos($this->value, '@');
        $domain = trim(substr($this->value, $separator + 1), '[]');

        return $domain;
    }

    /**
     * Returns the lowercase address without changing the stored value
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
