<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\Internet;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class IpV4Address
 */
final readonly class IpV4Address extends IpAddress
{
    /**
     * Constructs IpV4Address
     *
     * @throws DomainException When the value is not a bare IPv4 literal
     */
    private function __construct(private string $value)
    {
        if (!Validate::isIpV4Address($this->value)) {
            throw new DomainException('Invalid IPv4 address');
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
