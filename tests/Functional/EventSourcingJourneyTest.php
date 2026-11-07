<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Test\Common\Documentation\EventSourcingGuideExample;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversNothing]
final class EventSourcingJourneyTest extends UnitTestCase
{
    public static function compositions(): iterable
    {
        yield 'unchanged defaults' => [false, ['event_store_events', 'event_store_global_position',
            'projection_checkpoints', 'publication_cursors', 'publication_failures', 'publication_handler_failures']];
        yield 'all six configured' => [true, ['orders_events', 'orders_position', 'orders_projection_progress',
            'orders_publication_progress', 'orders_publication_errors', 'orders_handler_errors']];
    }

    #[DataProvider('compositions')]
    public function test_that_the_event_sourcing_journey_executes_through_public_contracts(
        bool $configured, array $tableNames,
    ): void {
        $result = EventSourcingGuideExample::run($configured);

        self::assertSame('Current name', $result['aggregate_name']);
        self::assertSame(2, $result['aggregate_version']);
        self::assertSame([], $result['pending_events']);
        self::assertSame(
            [
                $result['first_order_id'] => ['name' => 'Current name', 'global_position' => 2],
                $result['second_order_id'] => ['name' => 'Second order', 'global_position' => 3],
            ],
            $result['projection'],
        );
        self::assertSame(3, $result['projection_checkpoint']);
        self::assertSame(['Original name', 'Current name', 'Second order'], $result['dispatched_names']);
        self::assertSame(3, $result['publication_cursor']);
        self::assertSame(1, $result['publication_failures']);
        self::assertSame(1, $result['handler_failures']);
        self::assertSame(3, $result['global_position']);
        self::assertSame(array_combine($tableNames, [3, 1, 1, 1, 1, 1]), $result['table_counts']);
    }
}
