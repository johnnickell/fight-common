<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Execution\Dbal;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Fight\Common\Adapter\Mcp\Persistence\Execution\Dbal\DbalObservationTables;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DbalObservationTables::class)]
final class DbalObservationTablesTest extends UnitTestCase
{
    private const array DEFAULTS = [
        'schemaTable' => 'fight_execution_schema',
        'sessionTable' => 'fight_execution_sessions',
        'phaseTable' => 'fight_execution_phases',
        'observationTable' => 'fight_execution_observations',
        'contentTable' => 'fight_execution_contents',
    ];

    public function test_that_omitted_names_preserve_all_five_defaults(): void
    {
        $tables = new DbalObservationTables();
        foreach (self::DEFAULTS as $accessor => $name) {
            self::assertInstanceOf(DbalTableName::class, $tables->$accessor());
            self::assertSame($name, $tables->$accessor()->name());
            self::assertSame($tables->$accessor(), $tables->$accessor());
        }
    }

    public function test_that_all_selected_names_preserve_case_and_shared_identifier_quoting(): void
    {
        $names = ['Order', 'Group', 'Phase', 'Observation', '_'.str_repeat('a', 62)];
        $tables = new DbalObservationTables(...$names);
        foreach (array_keys(self::DEFAULTS) as $position => $accessor) {
            self::assertSame($names[$position], $tables->$accessor()->name());
            self::assertSame('`'.$names[$position].'`', $tables->$accessor()->schemaName());
            self::assertSame('"'.$names[$position].'"', $tables->$accessor()->quoted(new PostgreSQLPlatform()));
        }
    }

    #[DataProvider('tableParameters')]
    public function test_that_each_partial_override_preserves_omitted_defaults(string $parameter): void
    {
        $tables = new DbalObservationTables(...[$parameter => 'Selected']);
        foreach (self::DEFAULTS as $accessor => $name) {
            self::assertSame($parameter === $accessor ? 'Selected' : $name, $tables->$accessor()->name());
        }
    }

    public function test_that_an_override_cannot_collide_with_an_omitted_default(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Execution tables must have distinct names.');
        new DbalObservationTables(contentTable: 'FIGHT_EXECUTION_SCHEMA');
    }

    #[DataProvider('invalidNames')]
    public function test_that_invalid_names_reject_in_every_position(string $parameter, string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table names require');
        new DbalObservationTables(...[$parameter => $name]);
    }

    #[DataProvider('collidingNames')]
    public function test_that_every_pair_rejects_exact_and_case_only_collisions(array $names): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Execution tables must have distinct names.');
        new DbalObservationTables(...$names);
    }

    public static function tableParameters(): iterable
    {
        foreach (array_keys(self::DEFAULTS) as $parameter) {
            yield $parameter => [$parameter];
        }
    }

    public static function invalidNames(): iterable
    {
        foreach (array_keys(self::DEFAULTS) as $parameter) {
            foreach (['', 'schema.table', 'unsafe; DROP TABLE x', 'two words', '1table', '`quoted`', '"quoted"', 'café', str_repeat('a', 64)] as $position => $name) {
                yield $parameter.'-'.$position => [$parameter, $name];
            }
        }
    }

    public static function collidingNames(): iterable
    {
        $parameters = array_keys(self::DEFAULTS);
        foreach ($parameters as $left => $leftParameter) {
            foreach ($parameters as $right => $rightParameter) {
                if ($left >= $right) {
                    continue;
                }
                yield $leftParameter.'-'.$rightParameter.'-exact' => [[$leftParameter => 'Same', $rightParameter => 'Same']];
                yield $leftParameter.'-'.$rightParameter.'-case' => [[$leftParameter => 'Same', $rightParameter => 'SAME']];
            }
        }
    }
}
