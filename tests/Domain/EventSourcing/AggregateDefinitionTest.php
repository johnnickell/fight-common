<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\EventSourcing;

use Fight\Common\Domain\EventSourcing\AggregateDefinition;
use Fight\Common\Domain\EventSourcing\EventSourcedAggregate;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Identity\Identifier;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;

#[CoversClass(AggregateDefinition::class)]
class AggregateDefinitionTest extends UnitTestCase
{
    public function test_that_it_exposes_the_stable_aggregate_name_and_current_aggregate_class(): void
    {
        $definition = new AggregateDefinition('orders', DefinedAggregate::class);

        self::assertSame('orders', $definition->name());
        self::assertSame(DefinedAggregate::class, $definition->aggregateClass());
    }

    public function test_that_it_rejects_an_empty_stable_aggregate_name(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Aggregate name cannot be empty.');

        new AggregateDefinition('', DefinedAggregate::class);
    }

    #[DataProvider('invalid_aggregate_class_provider')]
    public function test_that_it_rejects_a_class_that_is_not_a_concrete_event_sourced_aggregate(string $class): void
    {
        $this->expectException(DomainException::class);

        new AggregateDefinition('orders', $class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalid_aggregate_class_provider(): iterable
    {
        yield 'aggregate interface' => [EventSourcedAggregate::class];
        yield 'abstract aggregate' => [AbstractDefinedAggregate::class];
        yield 'unrelated class' => [stdClass::class];
    }
}

abstract class AbstractDefinedAggregate implements EventSourcedAggregate
{
}

final class DefinedAggregate implements EventSourcedAggregate
{
    public function id(): Identifier
    {
        throw new RuntimeException('Not needed by this test.');
    }

    public function version(): int
    {
        return 0;
    }

    public function releaseEvents(): array
    {
        return [];
    }

    public static function reconstitute(iterable $events): static
    {
        throw new RuntimeException('Not needed by this test.');
    }
}
