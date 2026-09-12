<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Test\Common\Documentation\EventSourcingGuideExample;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class EventSourcingJourneyTest extends UnitTestCase
{
    public function test_that_the_event_sourcing_journey_executes_through_public_contracts(): void
    {
        $result = EventSourcingGuideExample::run();

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
    }
}
