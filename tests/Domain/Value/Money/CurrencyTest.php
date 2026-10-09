<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Money;

use Error;
use Fight\Common\Domain\Collection\HashSet;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StringObject;
use Fight\Common\Domain\Value\Money\Currency;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Currency::class)]
class CurrencyTest extends UnitTestCase
{
    #[DataProvider('supportedCodes')]
    public function test_that_every_selected_code_has_its_owned_accounting_exponent(string $code, int $exponent): void
    {
        $currency = Currency::fromCode($code);

        self::assertSame($code, $currency->code());
        self::assertSame('v1', $currency->definitionVersion());
        self::assertSame($exponent, $currency->accountingExponent());
        self::assertSame($code, $currency->toString());
        self::assertSame($code, (string) $currency);
        self::assertSame($code, $currency->jsonSerialize());
        self::assertSame(json_encode($code, JSON_THROW_ON_ERROR), json_encode($currency, JSON_THROW_ON_ERROR));
        self::assertTrue($currency->equals(Currency::fromString($currency->toString())));
        self::assertTrue($currency->equals(Currency::fromString(
            json_decode(json_encode($currency, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR)
        )));
        self::assertSame($code, $currency->hashValue());
        self::assertSame($exponent, Currency::fromDefinition($code, 'v1')->accountingExponent());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function supportedCodes(): iterable
    {
        foreach ([
            0 => ['CLP', 'JPY', 'KRW', 'VND'],
            2 => [
                'AED', 'ARS', 'AUD', 'BDT', 'BRL', 'CAD', 'CHF', 'CNY', 'COP', 'CZK', 'DKK', 'EGP', 'EUR',
                'GBP', 'HKD', 'HUF', 'IDR', 'ILS', 'INR', 'KES', 'LKR', 'MAD', 'MXN', 'MYR', 'NGN', 'NOK',
                'NZD', 'PEN', 'PHP', 'PKR', 'PLN', 'QAR', 'RON', 'RUB', 'SAR', 'SEK', 'SGD', 'THB', 'TRY',
                'TWD', 'UAH', 'USD', 'ZAR',
            ],
            3 => ['BHD', 'KWD'],
        ] as $exponent => $codes) {
            foreach ($codes as $code) {
                yield $code => [$code, $exponent];
            }
        }
    }

    #[DataProvider('unsupportedCodes')]
    public function test_that_unsupported_or_malformed_codes_are_never_given_a_default_scale(string $code): void
    {
        $this->expectException(DomainException::class);

        Currency::fromCode($code);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'unselected alphabetic code' => ['BGN'];
        yield 'unselected common code' => ['JPY '];
        yield 'lowercase' => ['usd'];
        yield 'mixed case' => ['Usd'];
        yield 'short code' => ['US'];
        yield 'numeric' => ['840'];
        yield 'non ASCII' => ['UŚD'];
        yield 'trailing newline' => ["USD\n"];
        yield 'embedded NUL' => ["US\0D"];
    }

    public function test_that_unsupported_definition_versions_reject_without_substitution(): void
    {
        $this->expectException(DomainException::class);

        Currency::fromDefinition('USD', 'v2');
    }

    public function test_that_unsupported_code_with_existing_version_rejects(): void
    {
        $this->expectException(DomainException::class);

        Currency::fromDefinition('BGN', 'v1');
    }

    public function test_that_code_identity_deduplicates_equal_values_in_real_collections(): void
    {
        $usd = Currency::fromCode('USD');
        $equal = Currency::fromDefinition('USD', 'v1');
        $other = Currency::fromCode('EUR');

        self::assertTrue($usd->equals($usd));
        self::assertNotSame($usd, $equal);
        self::assertTrue($usd->equals($equal));
        self::assertSame($usd->hashValue(), $equal->hashValue());
        self::assertFalse($usd->equals($other));
        self::assertFalse($usd->equals(StringObject::fromString('USD')));
        self::assertFalse($usd->equals('USD'));
        self::assertFalse($usd->equals(null));

        $set = HashSet::of(Currency::class);
        $set->add($usd);
        $set->add($equal);
        $set->add($other);
        self::assertSame(2, $set->count());
        self::assertTrue($set->contains(Currency::fromString('USD')));
        $set->remove(Currency::fromString('USD'));
        self::assertFalse($set->contains($usd));
        self::assertTrue($set->contains($other));
    }

    public function test_that_currency_cannot_acquire_mutable_state(): void
    {
        $usd = Currency::fromCode('USD');

        try {
            $usd->accountingExponent = 3;
            self::fail('Readonly currency must reject mutation');
        } catch (Error) {
            self::assertSame(2, $usd->accountingExponent());
            self::assertSame('USD', $usd->hashValue());
        }
    }
}
