<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Fight\Common\Adapter\EventSourcing\Dbal\DbalPublicationFailureTables;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DbalPublicationFailureTables::class)]
final class DbalPublicationFailureTablesTest extends UnitTestCase
{
    public function test_that_omitted_names_keep_exact_defaults_independently(): void
    {
        $defaults = new DbalPublicationFailureTables();
        self::assertSame('publication_failures', $defaults->failureTable()->name());
        self::assertSame('publication_handler_failures', $defaults->handlerFailureTable()->name());
        $parentOnly = new DbalPublicationFailureTables(failureTable: 'Order');
        self::assertSame('Order', $parentOnly->failureTable()->name());
        self::assertSame('publication_handler_failures', $parentOnly->handlerFailureTable()->name());
        $handlerOnly = new DbalPublicationFailureTables(handlerFailureTable: 'Group');
        self::assertSame('publication_failures', $handlerOnly->failureTable()->name());
        self::assertSame('Group', $handlerOnly->handlerFailureTable()->name());
    }

    public static function invalidSelections(): iterable
    {
        yield 'identical' => ['same', 'same'];
        yield 'case collision' => ['Order', 'order'];
        foreach (['', 'schema.table', 'bad-name', '`quoted`', 'a; DROP TABLE x', '9table', str_repeat('x', 64)] as $name) {
            yield 'parent '.$name => [$name, 'handlers'];
            yield 'handler '.$name => ['failures', $name];
        }
    }

    #[DataProvider('invalidSelections')]
    public function test_that_invalid_or_colliding_selections_reject(string $failureTable, string $handlerTable): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DbalPublicationFailureTables($failureTable, $handlerTable);
    }
}
