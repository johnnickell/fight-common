<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSourcing\InMemory;

use Fight\Common\Adapter\EventSourcing\InMemory\InMemoryPublicationCursorStore;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryPublicationCursorStore::class)]
final class InMemoryPublicationCursorStoreTest extends UnitTestCase
{
    public function test_that_named_publication_cursors_are_independent_and_cannot_move_backward(): void
    {
        $cursorStore = new InMemoryPublicationCursorStore();

        self::assertSame(0, $cursorStore->load('orders.primary'));
        self::assertSame(0, $cursorStore->load('orders.secondary'));

        $cursorStore->save('orders.primary', 3);
        $cursorStore->save('orders.secondary', 1);

        self::assertSame(3, $cursorStore->load('orders.primary'));
        self::assertSame(1, $cursorStore->load('orders.secondary'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Publication cursor orders.primary cannot move backward from 3 to 2.',
        );

        $cursorStore->save('orders.primary', 2);
    }
}
