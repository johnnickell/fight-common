<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\Money;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class CurrencyDefinitions
 *
 * Retains versioned accounting exponents independently of current selection
 *
 * @internal
 */
final readonly class CurrencyDefinitions
{
    /**
     * Constructs CurrencyDefinitions
     *
     * @param array<string, array<string, int>> $definitions Code => version => exponent
     * @param array<string, string> $current Code => current version
     */
    private function __construct(private array $definitions, private array $current)
    {
        foreach ($definitions as $code => $versions) {
            if (preg_match('/\A[A-Z]{3}\z/', (string) $code) !== 1 || $versions === []) {
                throw new DomainException('Invalid currency definition');
            }

            foreach ($versions as $version => $exponent) {
                if (
                    preg_match('/\Av[1-9][0-9]*\z/', (string) $version) !== 1
                    || !in_array($exponent, [0, 2, 3], true)
                ) {
                    throw new DomainException('Invalid currency definition');
                }
            }
        }

        if (count($definitions) !== count($current)) {
            throw new DomainException('Invalid current currency definitions');
        }

        foreach ($current as $code => $version) {
            if (!isset($definitions[$code][$version])) {
                throw new DomainException('Invalid current currency definitions');
            }
        }
    }

    /**
     * Creates a snapshot from maintainer-owned versioned definitions
     *
     * @param array<string, array<string, int>> $definitions Code => version => exponent
     * @param array<string, string> $current Code => current version
     */
    public static function fromDefinitions(array $definitions, array $current): self
    {
        return new self($definitions, $current);
    }

    /**
     * Creates the package-owned definitions; every initial code starts at v1
     */
    public static function standard(): self
    {
        $byExponent = [
            0 => ['CLP', 'JPY', 'KRW', 'VND'],
            2 => [
                'AED', 'ARS', 'AUD', 'BDT', 'BRL', 'CAD', 'CHF', 'CNY', 'COP', 'CZK', 'DKK', 'EGP', 'EUR',
                'GBP', 'HKD', 'HUF', 'IDR', 'ILS', 'INR', 'KES', 'LKR', 'MAD', 'MXN', 'MYR', 'NGN', 'NOK',
                'NZD', 'PEN', 'PHP', 'PKR', 'PLN', 'QAR', 'RON', 'RUB', 'SAR', 'SEK', 'SGD', 'THB', 'TRY',
                'TWD', 'UAH', 'USD', 'ZAR'
            ],
            3 => ['BHD', 'KWD']
        ];
        $definitions = [];
        $current = [];
        foreach ($byExponent as $exponent => $codes) {
            foreach ($codes as $code) {
                $definitions[$code] = ['v1' => $exponent];
                $current[$code] = 'v1';
            }
        }

        return new self($definitions, $current);
    }

    /**
     * Creates a replacement snapshot only if every previously readable definition is retained unchanged
     *
     * @param array<string, array<string, int>> $definitions Code => version => exponent
     * @param array<string, string> $current Code => current version
     */
    public function revised(array $definitions, array $current): self
    {
        $next = new self($definitions, $current);
        foreach ($this->definitions as $code => $versions) {
            foreach ($versions as $version => $exponent) {
                if (($next->definitions[$code][$version] ?? null) !== $exponent) {
                    throw new DomainException('Currency definition cannot be replaced');
                }
            }
        }

        return $next;
    }

    /**
     * Returns the selected version for a supported code
     */
    public function currentVersion(string $code): string
    {
        return $this->current[$code] ?? throw new DomainException('Unsupported currency code');
    }

    /**
     * Returns the retained exponent for an exact code and definition version
     */
    public function exponent(string $code, string $version): int
    {
        return $this->definitions[$code][$version] ?? throw new DomainException('Unsupported currency definition');
    }
}
