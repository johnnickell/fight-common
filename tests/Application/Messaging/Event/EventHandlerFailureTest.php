<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Messaging\Event;

use Fight\Common\Application\Messaging\Event\EventHandlerFailure;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(EventHandlerFailure::class)]
class EventHandlerFailureTest extends UnitTestCase
{
    public function test_that_accessors_return_the_constructor_values(): void
    {
        $throwable = new RuntimeException('handler failed');

        $failure = new EventHandlerFailure('SampleHandler::handle', $throwable);

        self::assertSame('SampleHandler::handle', $failure->callableDescription());
        self::assertSame($throwable, $failure->throwable());
    }
}
