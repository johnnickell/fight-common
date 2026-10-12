<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Money;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Money\CurrencyDefinitions;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(CurrencyDefinitions::class)]
class CurrencyDefinitionsTest extends UnitTestCase
{
    public function test_that_standard_snapshot_has_selected_versions_and_scales(): void
    {
        $standard = CurrencyDefinitions::standard();

        self::assertSame('v1', $standard->currentVersion('USD'));
        self::assertSame(2, $standard->exponent('USD', 'v1'));
        self::assertSame(0, $standard->exponent('JPY', 'v1'));
        self::assertSame(3, $standard->exponent('KWD', 'v1'));
    }

    public function test_that_additions_and_explicit_revisions_preserve_saved_definition_meaning(): void
    {
        $original = CurrencyDefinitions::fromDefinitions(['USD' => ['v1' => 2]], ['USD' => 'v1']);
        $updated = $original->revised(
            ['USD' => ['v1' => 2, 'v2' => 3], 'JPY' => ['v1' => 0]],
            ['USD' => 'v2', 'JPY' => 'v1']
        );

        self::assertSame('v1', $original->currentVersion('USD'));
        self::assertSame(2, $original->exponent('USD', 'v1'));
        self::assertSame('v2', $updated->currentVersion('USD'));
        self::assertSame(3, $updated->exponent('USD', 'v2'));
        self::assertSame(2, $updated->exponent('USD', 'v1'));
        self::assertSame(0, $updated->exponent('JPY', $updated->currentVersion('JPY')));
    }

    #[DataProvider('invalidDefinitions')]
    public function test_that_invalid_definition_snapshots_reject(array $definitions, array $current): void
    {
        $this->expectException(DomainException::class);

        CurrencyDefinitions::fromDefinitions($definitions, $current);
    }

    /**
     * @return iterable<string, array{array<mixed>, array<mixed>}>
     */
    public static function invalidDefinitions(): iterable
    {
        yield 'malformed code' => [['UsD' => ['v1' => 2]], ['UsD' => 'v1']];
        yield 'numeric code' => [[840 => ['v1' => 2]], [840 => 'v1']];
        yield 'empty definitions' => [['USD' => []], ['USD' => 'v1']];
        yield 'invalid version' => [['USD' => ['v0' => 2]], ['USD' => 'v0']];
        yield 'noninteger scale' => [['USD' => ['v1' => '2']], ['USD' => 'v1']];
        yield 'unsupported scale' => [['USD' => ['v1' => 4]], ['USD' => 'v1']];
        yield 'missing current code' => [['USD' => ['v1' => 2]], []];
        yield 'unrecognized current code' => [['USD' => ['v1' => 2]], ['EUR' => 'v1']];
        yield 'unrecognized current version' => [['USD' => ['v1' => 2]], ['USD' => 'v2']];
    }

    public function test_that_revision_cannot_change_an_existing_exponent(): void
    {
        $original = CurrencyDefinitions::fromDefinitions(['USD' => ['v1' => 2]], ['USD' => 'v1']);
        $this->expectException(DomainException::class);

        $original->revised(['USD' => ['v1' => 3]], ['USD' => 'v1']);
    }

    public function test_that_revision_cannot_remove_an_existing_definition(): void
    {
        $original = CurrencyDefinitions::fromDefinitions(['USD' => ['v1' => 2]], ['USD' => 'v1']);
        $this->expectException(DomainException::class);

        $original->revised(['JPY' => ['v1' => 0]], ['JPY' => 'v1']);
    }

    public function test_that_unknown_code_and_version_have_no_fallback(): void
    {
        $definitions = CurrencyDefinitions::fromDefinitions(['USD' => ['v1' => 2]], ['USD' => 'v1']);

        try {
            $definitions->currentVersion('EUR');
            self::fail('Unknown current code must reject');
        } catch (DomainException) {
            self::assertSame(2, $definitions->exponent('USD', 'v1'));
        }

        $this->expectException(DomainException::class);
        $definitions->exponent('USD', 'v2');
    }
}
