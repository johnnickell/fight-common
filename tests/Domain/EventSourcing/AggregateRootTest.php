<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\EventSourcing;

use Fight\Common\Domain\EventSourcing\AggregateRoot;
use Fight\Common\Domain\EventSourcing\EventSourcedAggregate;
use Fight\Common\Domain\EventSourcing\Exception\UnrecognizedEventException;
use Fight\Common\Domain\Identity\UniqueId;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(AggregateRoot::class)]
class AggregateRootTest extends UnitTestCase
{
    public function test_that_reconstitute_restores_a_private_aggregate_without_recording_events(): void
    {
        $history = static function (): iterable {
            yield new ConsumerAggregateCreated(
                ConsumerAggregateId::fromString('6ff84280-669f-40ba-b974-dc27587b17aa'),
                'created',
            );
            yield new ValueChanged('first');
            yield new ValueChanged('restored');
        };

        $aggregate = ConsumerAggregate::reconstitute($history());

        self::assertInstanceOf(EventSourcedAggregate::class, $aggregate);
        self::assertSame('6ff84280-669f-40ba-b974-dc27587b17aa', $aggregate->id()->toString());
        self::assertSame('restored', $aggregate->value());
        self::assertSame(3, $aggregate->version());
        self::assertSame([], $aggregate->releaseEvents());
    }

    public function test_that_release_events_returns_recorded_events_in_order_and_clears_pending_state(): void
    {
        $aggregate = ConsumerAggregate::create(
            ConsumerAggregateId::fromString('0af734ab-e953-4394-8875-fbf5cf986e76'),
        );

        $aggregate->changeValue(new ValueChanged('first'));
        $aggregate->changeValue(new ValueChanged('second'));

        self::assertSame(2, $aggregate->version());
        self::assertEquals(
            [new ValueChanged('first'), new ValueChanged('second')],
            $aggregate->releaseEvents(),
        );
        self::assertSame([], $aggregate->releaseEvents());
    }

    public function test_that_record_applies_before_advancing_the_pending_event_lifecycle(): void
    {
        $id = ConsumerAggregateId::fromString('f6d56854-655f-4b41-a7c5-f69738452c5c');
        $aggregate = ConsumerAggregate::create($id);

        self::assertInstanceOf(EventSourcedAggregate::class, $aggregate);
        self::assertSame($id, $aggregate->id());
        self::assertSame(0, $aggregate->version());
        self::assertSame([], $aggregate->releaseEvents());

        $changed = new ValueChanged('applied');
        $aggregate->changeValue($changed);

        self::assertSame('applied', $aggregate->value());
        self::assertSame(1, $aggregate->version());
        self::assertSame([$changed], $aggregate->releaseEvents());

        $unrecognized = ConsumerAggregate::create($id);

        try {
            $unrecognized->recordUnrecognized(new UnrecognizedEvent());
            self::fail('Expected an unrecognized event to fail closed.');
        } catch (UnrecognizedEventException) {
            self::assertSame(0, $unrecognized->version());
            self::assertSame([], $unrecognized->releaseEvents());
        }

        $failed = ConsumerAggregate::create($id);

        try {
            $failed->failToApply(new ApplicationFailed());
            self::fail('Expected failed event application to fail closed.');
        } catch (RuntimeException) {
            self::assertSame(0, $failed->version());
            self::assertSame([], $failed->releaseEvents());
        }

        $noOp = ConsumerAggregate::create($id);
        $ignored = new ExplicitlyIgnored();
        $noOp->ignore($ignored);

        self::assertSame(1, $noOp->version());
        self::assertSame([$ignored], $noOp->releaseEvents());
    }
}

final readonly class ConsumerAggregateId extends UniqueId
{
}

final readonly class ConsumerAggregateCreated implements Event
{
    public function __construct(
        public ConsumerAggregateId $id,
        public string $value,
    ) {
    }

    public static function fromArray(array $data): static
    {
        return new self(
            ConsumerAggregateId::fromString($data['id']),
            $data['value'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'value' => $this->value,
        ];
    }
}

final class ConsumerAggregate extends AggregateRoot
{
    private string $value = 'initial';

    private function __construct(ConsumerAggregateId $id)
    {
        parent::__construct($id);
    }

    public static function create(ConsumerAggregateId $id): self
    {
        return new self($id);
    }

    public static function reconstitute(iterable $events): static
    {
        $aggregate = null;

        foreach ($events as $event) {
            if (null === $aggregate) {
                if (!$event instanceof ConsumerAggregateCreated) {
                    throw new RuntimeException('Aggregate history must begin with its creation event.');
                }

                $aggregate = new static($event->id);
            }

            $aggregate->replay($event);
        }

        return $aggregate ?? throw new RuntimeException('Aggregate history cannot be empty.');
    }

    public function value(): string
    {
        return $this->value;
    }

    public function changeValue(ValueChanged $event): void
    {
        $this->record($event);
    }

    public function recordUnrecognized(UnrecognizedEvent $event): void
    {
        $this->record($event);
    }

    public function failToApply(ApplicationFailed $event): void
    {
        $this->record($event);
    }

    public function ignore(ExplicitlyIgnored $event): void
    {
        $this->record($event);
    }

    protected function apply(Event $event): void
    {
        match (true) {
            $event instanceof ConsumerAggregateCreated => $this->whenConsumerAggregateCreated($event),
            $event instanceof ValueChanged => $this->whenValueChanged($event),
            $event instanceof ApplicationFailed => $this->whenApplicationFailed(),
            $event instanceof ExplicitlyIgnored => $this->whenExplicitlyIgnored(),
            default => throw new UnrecognizedEventException($event),
        };
    }

    private function whenConsumerAggregateCreated(ConsumerAggregateCreated $event): void
    {
        $this->value = $event->value;
    }

    private function whenValueChanged(ValueChanged $event): void
    {
        $this->value = $event->value;
    }

    private function whenApplicationFailed(): void
    {
        throw new RuntimeException('Event application failed.');
    }

    private function whenExplicitlyIgnored(): void
    {
    }
}

final readonly class ValueChanged implements Event
{
    public function __construct(public string $value)
    {
    }

    public static function fromArray(array $data): static
    {
        return new self($data['value']);
    }

    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}

final readonly class UnrecognizedEvent implements Event
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

final readonly class ApplicationFailed implements Event
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

final readonly class ExplicitlyIgnored implements Event
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
