<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release;

/**
 * Class StableSemVer
 *
 * Applies strict stable SemVer validation and arbitrary-length increments.
 */
final class StableSemVer
{
    /** @var array<string, int> */
    private const array SEGMENTS = ['major' => 0, 'minor' => 1, 'patch' => 2];

    /**
     * Checks the supported three-segment stable SemVer form
     */
    public static function isValid(string $version): bool
    {
        return preg_match(
            '/^(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)$/D',
            $version
        ) === 1;
    }

    /**
     * Returns the exact canonical increment without platform integer conversion
     */
    public static function increment(string $version, string $releaseClass): ?string
    {
        if (!self::isValid($version) || !isset(self::SEGMENTS[$releaseClass])) {
            return null;
        }

        $segment = self::SEGMENTS[$releaseClass];
        $segments = explode('.', $version);
        $segments[$segment] = self::incrementDecimal($segments[$segment]);

        for ($index = $segment + 1; $index < 3; $index++) {
            $segments[$index] = '0';
        }

        return implode('.', $segments);
    }

    /**
     * Returns canonical version ordering without platform integer conversion
     */
    public static function compare(string $left, string $right): ?int
    {
        if (!self::isValid($left) || !self::isValid($right)) {
            return null;
        }

        $leftSegments = explode('.', $left);
        $rightSegments = explode('.', $right);

        for ($index = 0; $index < 3; $index++) {
            $length = strlen($leftSegments[$index]) <=> strlen($rightSegments[$index]);

            if ($length !== 0) {
                return $length;
            }

            $value = strcmp($leftSegments[$index], $rightSegments[$index]);

            if ($value !== 0) {
                return $value <=> 0;
            }
        }

        return 0;
    }

    /**
     * Returns the next arbitrarily large unsigned decimal identifier
     */
    private static function incrementDecimal(string $decimal): string
    {
        $digits = str_split($decimal);

        for ($index = count($digits) - 1; $index >= 0; $index--) {
            if ($digits[$index] !== '9') {
                $digits[$index] = chr(ord($digits[$index]) + 1);

                return implode('', $digits);
            }

            $digits[$index] = '0';
        }

        return '1'.implode('', $digits);
    }
}
