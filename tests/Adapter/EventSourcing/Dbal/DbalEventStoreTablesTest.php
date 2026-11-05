<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\Dbal;

use Fight\Common\Adapter\EventSourcing\Dbal\DbalEventStoreTables;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DbalEventStoreTables::class)]
final class DbalEventStoreTablesTest extends UnitTestCase
{
    public function test_that_omitted_names_preserve_independent_defaults(): void
    {
        $defaultTables = new DbalEventStoreTables();
        self::assertSame('event_store_events', $defaultTables->eventTable()->name());
        self::assertSame('event_store_global_position', $defaultTables->globalPositionTable()->name());
        $eventOverride = new DbalEventStoreTables(eventTable: 'Order');
        self::assertSame('Order', $eventOverride->eventTable()->name());
        self::assertSame('event_store_global_position', $eventOverride->globalPositionTable()->name());
        $positionOverride = new DbalEventStoreTables(globalPositionTable: 'Group');
        self::assertSame('event_store_events', $positionOverride->eventTable()->name());
        self::assertSame('Group', $positionOverride->globalPositionTable()->name());
    }

    #[DataProvider('collidingNames')]
    public function test_that_case_insensitive_collisions_reject(string $eventTable, string $positionTable): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DbalEventStoreTables($eventTable, $positionTable);
    }

    public function test_that_invalid_event_or_sequence_names_never_fall_back_to_defaults(): void
    {
        foreach ([['unsafe.events', 'sequence'], ['events', 'sequence; DROP TABLE events']] as [$eventTable, $positionTable]) {
            try {
                new DbalEventStoreTables($eventTable, $positionTable);
                self::fail('Invalid selections must reject, not silently select default tables.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('Table names require', $exception->getMessage());
            }
        }
    }

    public static function collidingNames(): iterable
    {
        yield ['events', 'events'];
        yield ['Events', 'EVENTS'];
    }
}
