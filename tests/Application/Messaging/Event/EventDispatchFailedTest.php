<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Messaging\Event;

use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Application\Messaging\Event\EventHandlerFailure;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(EventDispatchFailed::class)]
class EventDispatchFailedTest extends UnitTestCase
{
    public function test_that_constructor_reports_and_preserves_failures_in_order(): void
    {
        $first = new EventHandlerFailure('FirstHandler::handle', new RuntimeException('first failure'));
        $second = new EventHandlerFailure('SecondHandler::handle', new RuntimeException('second failure'));

        $failed = new EventDispatchFailed([$first, $second]);

        self::assertSame('Event dispatch failed in 2 handler(s).', $failed->getMessage());
        self::assertSame([$first, $second], $failed->failures());
    }
}
