<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Persistence\Doctrine;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Fight\Common\Adapter\Persistence\Doctrine\DbalTableName;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DbalTableName::class)]
final class DbalTableNameTest extends UnitTestCase
{
    public function test_that_names_retain_case_and_use_platform_quoting(): void
    {
        foreach (['Order', '_state_42', str_repeat('a', 63)] as $name) {
            $tableName = new DbalTableName($name);
            self::assertSame($name, $tableName->name());
            self::assertSame('`'.$name.'`', $tableName->schemaName());
            self::assertSame('`'.$name.'`', $tableName->quoted(new MySQLPlatform()));
            self::assertSame('"'.$name.'"', $tableName->quoted(new PostgreSQLPlatform()));
            self::assertSame('"'.$name.'"', $tableName->quoted(new SQLitePlatform()));
        }
    }

    #[DataProvider('invalidNames')]
    public function test_that_unsupported_names_reject_at_construction(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DbalTableName($name);
    }

    public static function invalidNames(): iterable
    {
        foreach (['', '1events', 'a.b', 'a-b', 'a b', ' a', 'a ', 'évents', '"order"', '`order`',
            'events; DROP TABLE events', "events\n", "events\0", str_repeat('a', 64)] as $name) {
            yield [$name];
        }
    }
}
