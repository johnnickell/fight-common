<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\Internet;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class IpV6Address
 */
final readonly class IpV6Address extends IpAddress
{
    private string $value;

    /**
     * Constructs IpV6Address
     *
     * @throws DomainException When the value is not a bare IPv6 literal
     */
    private function __construct(string $value)
    {
        if (!Validate::isIpV6Address($value)) {
            throw new DomainException('Invalid IPv6 address');
        }

        // Validated literals always encode to sixteen bytes; render them independently of native formatter policy.
        $this->value = self::canonicalize((string) inet_pton($value));
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

    /**
     * Returns lowercase hexadecimal with the first longest multi-zero run compressed
     *
     * Mapped addresses use hexadecimal hextets, never a dotted IPv4 suffix.
     */
    private static function canonicalize(string $packed): string
    {
        $groups = array_map(
            static fn(string $group): string => dechex((int) hexdec($group)),
            str_split(bin2hex($packed), 4)
        );
        $bestStart = 0;
        $bestLength = 1;
        $runStart = 0;
        $runLength = 0;
        foreach ($groups as $index => $group) {
            if ($group !== '0') {
                $runLength = 0;
                continue;
            }

            if ($runLength === 0) {
                $runStart = $index;
            }

            $runLength++;
            if ($runLength > $bestLength) {
                $bestStart = $runStart;
                $bestLength = $runLength;
            }
        }

        if ($bestLength === 1) {
            return implode(':', $groups);
        }

        $prefix = implode(':', array_slice($groups, 0, $bestStart));
        $suffix = implode(':', array_slice($groups, $bestStart + $bestLength));

        return $prefix.'::'.$suffix;
    }
}
