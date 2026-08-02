<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\EventSourcing\Exception;

use Fight\Common\Domain\EventSourcing\Exception\UnrecognizedEventException;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UnrecognizedEventException::class)]
class UnrecognizedEventExceptionTest extends UnitTestCase
{
    public function test_that_constructor_identifies_the_unrecognized_event_by_fully_qualified_class_name(): void
    {
        $exception = new UnrecognizedEventException(new UnrecognizedExceptionTestEvent());

        self::assertInstanceOf(DomainException::class, $exception);
        self::assertSame(
            sprintf('Unrecognized event: %s', UnrecognizedExceptionTestEvent::class),
            $exception->getMessage(),
        );
    }
}

final readonly class UnrecognizedExceptionTestEvent implements Event
{
    public static function fromArray(array $data): static
    {
        return new self();
    }

    public function toArray(): array
    {
        return [];
    }
}
