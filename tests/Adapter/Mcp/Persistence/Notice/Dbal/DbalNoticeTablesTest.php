<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Persistence\Notice\Dbal;

use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTables;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DbalNoticeTables::class)]
final class DbalNoticeTablesTest extends UnitTestCase
{
    public function test_that_defaults_and_each_independent_partial_override_preserve_omitted_names(): void
    {
        $defaults = ['fight_notice_state', 'fight_notices', 'fight_notice_relationships', 'fight_notice_comparisons'];
        $arguments = ['stateTable', 'noticeTable', 'relationshipTable', 'comparisonTable'];
        self::assertSame($defaults, $this->names(new DbalNoticeTables()));
        foreach ($arguments as $index => $argument) {
            $expected = $defaults; $expected[$index] = 'Order';
            self::assertSame($expected, $this->names(new DbalNoticeTables(...[$argument => 'Order'])));
        }
        self::assertSame(['State', 'Order', 'Relations', 'Comparisons'], $this->names(new DbalNoticeTables('State', 'Order', 'Relations', 'Comparisons')));
    }

    #[DataProvider('invalidNames')]
    public function test_that_every_selection_uses_shared_ordinary_identifier_validation(string $argument, string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DbalNoticeTables(...[$argument => $name]);
    }

    public static function invalidNames(): iterable
    {
        foreach (['stateTable', 'noticeTable', 'relationshipTable', 'comparisonTable'] as $argument) {
            foreach (['', 'schema.notices', 'a; DROP TABLE fight_notices', 'a b', '`quoted`', '"quoted"', '雪', '1notice', str_repeat('a', 64), "name\n"] as $name) {
                yield [$argument, $name];
            }
        }
    }

    #[DataProvider('collisions')]
    public function test_that_all_case_insensitive_table_collisions_reject(int $first, int $second): void
    {
        $names = ['state', 'notices', 'relations', 'comparisons'];
        $names[$first] = 'Same'; $names[$second] = 'sAME';
        $this->expectException(InvalidArgumentException::class);
        new DbalNoticeTables(...$names);
    }

    public static function collisions(): iterable
    {
        for ($first = 0; $first < 4; ++$first) {
            for ($second = $first + 1; $second < 4; ++$second) { yield [$first, $second]; }
        }
    }

    private function names(DbalNoticeTables $tables): array
    {
        return array_map(static fn ($table) => $table->name(), [$tables->stateTable(), $tables->noticeTable(), $tables->relationshipTable(), $tables->comparisonTable()]);
    }
}
