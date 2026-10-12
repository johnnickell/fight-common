<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\Internet;

use Fight\Common\Domain\Utility\Validate;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class IpAddress
 *
 * This hierarchy supports only the two package-owned address families.
 * Valid syntax does not confer network permission or reachability.
 */
abstract readonly class IpAddress extends ValueObject
{
    /**
     * @inheritDoc
     */
    public static function fromString(string $value): static
    {
        $address = IpV6Address::class;
        if (Validate::isIpV4Address($value)) {
            $address = IpV4Address::class;
        }

        // Only IpAddress uses this dispatch; both supported final families override it with their own factories.
        /**
         * @var class-string<static> $address
         */
        return $address::fromString($value);
    }
}
