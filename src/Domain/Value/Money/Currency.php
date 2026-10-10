<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\Money;

use Fight\Common\Domain\Value\ValueObject;

/**
 * Class Currency
 *
 * Code is identity; a retained definition version and exponent describe accounting precision
 */
final readonly class Currency extends ValueObject
{
    /**
     * Constructs Currency
     */
    private function __construct(
        private string $code,
        private string $definitionVersion,
        private int $accountingExponent
    ) {
    }

    /**
     * Creates a currency using its current supported accounting definition
     */
    public static function fromCode(string $code): self
    {
        $definitions = CurrencyDefinitions::standard();
        $version = $definitions->currentVersion($code);

        return new self($code, $version, $definitions->exponent($code, $version));
    }

    /**
     * Creates a currency from a retained definition for saved monetary values
     */
    public static function fromDefinition(string $code, string $version): self
    {
        return new self($code, $version, CurrencyDefinitions::standard()->exponent($code, $version));
    }

    /**
     * Creates a currency from a controlled definition snapshot for package maintenance evidence
     *
     * @internal
     */
    public static function fromDefinitions(string $code, string $version, CurrencyDefinitions $definitions): self
    {
        return new self($code, $version, $definitions->exponent($code, $version));
    }

    /**
     * @inheritDoc
     */
    public static function fromString(string $value): static
    {
        return self::fromCode($value);
    }

    /**
     * Returns the exact alphabetic code
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * Returns the retained accounting definition version
     */
    public function definitionVersion(): string
    {
        return $this->definitionVersion;
    }

    /**
     * Returns the base-ten exponent for accounting minor units, not a cash rounding rule
     */
    public function accountingExponent(): int
    {
        return $this->accountingExponent;
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return $this->code;
    }
}
