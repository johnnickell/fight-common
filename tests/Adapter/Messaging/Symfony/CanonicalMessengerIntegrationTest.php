<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Messaging\Symfony;

use DateTimeImmutable;
use Fight\Common\Adapter\Messaging\Command\Async\MessengerCommandBus as LegacyMessengerCommandBus;
use Fight\Common\Adapter\Messaging\Event\Async\MessengerEventDispatcher as LegacyMessengerEventDispatcher;
use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\SymfonyCommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\SymfonyEventMessageHandler;
use Fight\Common\Adapter\Messaging\Serializer\SymfonyMessageSerializer as LegacySymfonyMessageSerializer;
use Fight\Common\Adapter\Messaging\Symfony\MessengerCommandBus;
use Fight\Common\Adapter\Messaging\Symfony\MessengerEventDispatcher;
use Fight\Common\Adapter\Messaging\Symfony\Serializer\SymfonyMessageSerializer;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Fight\Common\Application\Serialization\JsonSerializer;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

#[CoversClass(CommandMessageHandler::class)]
#[CoversClass(EventMessageHandler::class)]
#[CoversClass(MessengerCommandBus::class)]
#[CoversClass(MessengerEventDispatcher::class)]
#[CoversClass(SymfonyMessageSerializer::class)]
final class CanonicalMessengerIntegrationTest extends UnitTestCase
{
    public function test_that_real_symfony_messenger_registration_keeps_neutral_and_legacy_handler_identities_distinct(): void
    {
        $commandBus = $this->mock(SynchronousCommandBus::class);
        $eventDispatcher = $this->mock(SynchronousEventDispatcher::class);
        $container = new ContainerBuilder();
        $container->register(CommandMessageHandler::class, CommandMessageHandler::class)
            ->setArguments([$commandBus])
            ->addTag('messenger.message_handler', ['bus' => 'canonical.command']);
        $container->register(EventMessageHandler::class, EventMessageHandler::class)
            ->setArguments([$eventDispatcher])
            ->addTag('messenger.message_handler', ['bus' => 'canonical.event']);
        $container->register(SymfonyCommandMessageHandler::class, SymfonyCommandMessageHandler::class)
            ->setArguments([$commandBus])
            ->addTag('messenger.message_handler', ['bus' => 'legacy.command']);
        $container->register(SymfonyEventMessageHandler::class, SymfonyEventMessageHandler::class)
            ->setArguments([$eventDispatcher])
            ->addTag('messenger.message_handler', ['bus' => 'legacy.event']);

        foreach (['canonical.command', 'canonical.event', 'legacy.command', 'legacy.event'] as $bus) {
            $container->register($bus)->addTag('messenger.bus');
        }

        (new MessengerPass())->process($container);

        foreach ([
            CommandMessageHandler::class,
            EventMessageHandler::class,
            SymfonyCommandMessageHandler::class,
            SymfonyEventMessageHandler::class,
        ] as $handler) {
            self::assertSame([], (new \ReflectionClass($handler))->getAttributes(AsMessageHandler::class));
            self::assertArrayHasKey('messenger.message_handler', $container->getDefinition($handler)->getTags());
        }

        self::assertSame(
            [CommandMessage::class],
            array_keys($this->registeredMessageTypes($container, 'canonical.command')),
        );
        self::assertSame(
            [EventMessage::class],
            array_keys($this->registeredMessageTypes($container, 'canonical.event')),
        );
        self::assertSame(
            [CommandMessage::class],
            array_keys($this->registeredMessageTypes($container, 'legacy.command')),
        );
        self::assertSame(
            [EventMessage::class],
            array_keys($this->registeredMessageTypes($container, 'legacy.event')),
        );
    }

    public function test_that_canonical_and_legacy_messenger_buses_preserve_complete_envelopes_for_the_same_transport(): void
    {
        $command = $this->commandMessage();
        $event = $this->eventMessage();
        $sender = $this->mock(SenderInterface::class);
        $sender->shouldReceive('send')->times(4)->andReturnUsing(
            static function (Envelope $envelope) use ($command, $event): Envelope {
                self::assertTrue($envelope->getMessage() === $command || $envelope->getMessage() === $event);

                return $envelope;
            }
        );

        (new MessengerCommandBus($sender))->dispatch($command);
        (new LegacyMessengerCommandBus($sender))->dispatch($command);
        (new MessengerEventDispatcher($sender))->dispatch($event);
        (new LegacyMessengerEventDispatcher($sender))->dispatch($event);
    }

    public function test_that_canonical_command_bus_execute_wraps_and_sends_the_command(): void
    {
        $command = new CanonicalMessengerCommand('command-value');
        $sender = $this->mock(SenderInterface::class);
        $sender->shouldReceive('send')->once()->andReturnUsing(
            static function (Envelope $envelope) use ($command): Envelope {
                $message = $envelope->getMessage();

                self::assertInstanceOf(CommandMessage::class, $message);
                self::assertSame($command, $message->payload());

                return $envelope;
            }
        );

        (new MessengerCommandBus($sender))->execute($command);
    }

    public function test_that_canonical_event_dispatcher_trigger_wraps_and_sends_the_event(): void
    {
        $event = new CanonicalMessengerEvent('event-value');
        $sender = $this->mock(SenderInterface::class);
        $sender->shouldReceive('send')->once()->andReturnUsing(
            static function (Envelope $envelope) use ($event): Envelope {
                $message = $envelope->getMessage();

                self::assertInstanceOf(EventMessage::class, $message);
                self::assertSame($event, $message->payload());

                return $envelope;
            }
        );

        (new MessengerEventDispatcher($sender))->trigger($event);
    }

    public function test_that_canonical_event_dispatcher_registers_and_unregisters_no_local_subscribers(): void
    {
        $sender = $this->mock(SenderInterface::class);
        $dispatcher = new MessengerEventDispatcher($sender);
        $subscriber = new class implements EventSubscriber {
            public static function eventRegistration(): array
            {
                return [CanonicalMessengerEvent::class => 'handle'];
            }
        };

        $dispatcher->register($subscriber);
        $dispatcher->unregister($subscriber);

        self::assertFalse($dispatcher->hasHandlers());
        self::assertSame([], $dispatcher->getHandlers());
    }

    public function test_that_canonical_event_dispatcher_keeps_handler_operations_as_no_ops(): void
    {
        $sender = $this->mock(SenderInterface::class);
        $dispatcher = new MessengerEventDispatcher($sender);
        $handler = static function (): void {
        };

        $dispatcher->addHandler(CanonicalMessengerEvent::class, $handler, 100);

        self::assertFalse($dispatcher->hasHandlers());
        self::assertFalse($dispatcher->hasHandlers(CanonicalMessengerEvent::class));
        self::assertSame([], $dispatcher->getHandlers());
        self::assertSame([], $dispatcher->getHandlers(CanonicalMessengerEvent::class));

        $dispatcher->removeHandler(CanonicalMessengerEvent::class, $handler);

        self::assertFalse($dispatcher->hasHandlers(CanonicalMessengerEvent::class));
    }

    public function test_that_canonical_serializer_unserialize_callback_throws_message_decoding_failed_exception(): void
    {
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage('Message class "NonExistentCanonicalClassAbc" not found during decoding');

        SymfonyMessageSerializer::handleUnserializeCallback('NonExistentCanonicalClassAbc');
    }

    public function test_that_canonical_and_legacy_serializers_round_trip_complete_command_and_event_envelopes(): void
    {
        $commandEnvelope = new Envelope($this->commandMessage(), [new BusNameStamp('commands')]);
        $eventEnvelope = new Envelope($this->eventMessage(), [new BusNameStamp('events')]);

        foreach ([
            new SymfonyMessageSerializer(new JsonSerializer()),
            new LegacySymfonyMessageSerializer(new JsonSerializer()),
        ] as $serializer) {
            foreach ([$commandEnvelope, $eventEnvelope] as $envelope) {
                $decoded = $serializer->decode($serializer->encode($envelope));

                self::assertSame($envelope->getMessage()->toArray(), $decoded->getMessage()->toArray());
                self::assertSame(
                    $envelope->getMessage()->timestamp()->format('Y-m-d\\TH:i:s.uP'),
                    $decoded->getMessage()->timestamp()->format('Y-m-d\\TH:i:s.uP'),
                );
                self::assertSame(
                    $envelope->last(BusNameStamp::class)?->getBusName(),
                    $decoded->last(BusNameStamp::class)?->getBusName(),
                );
            }
        }
    }

    private function commandMessage(): CommandMessage
    {
        return new CommandMessage(
            MessageId::generate(),
            new DateTimeImmutable('2026-08-24 12:34:56.123456+00:00'),
            new CanonicalMessengerCommand('command-value'),
            Meta::create(['trace_id' => 'command-trace', 'attempt' => 2]),
        );
    }

    private function eventMessage(): EventMessage
    {
        return new EventMessage(
            MessageId::generate(),
            new DateTimeImmutable('2026-08-24 12:34:56.123456+00:00'),
            new CanonicalMessengerEvent('event-value'),
            Meta::create(['trace_id' => 'event-trace', 'attempt' => 2]),
        );
    }

    /** @return array<string, mixed> */
    private function registeredMessageTypes(ContainerBuilder $container, string $bus): array
    {
        return $container->getDefinition($bus.'.messenger.handlers_locator')->getArgument(0);
    }
}

final readonly class CanonicalMessengerCommand implements Command
{
    public function __construct(private string $value)
    {
    }

    public static function fromArray(array $data): static
    {
        return new static($data['value']);
    }

    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}

final readonly class CanonicalMessengerEvent implements Event
{
    public function __construct(private string $value)
    {
    }

    public static function fromArray(array $data): static
    {
        return new static($data['value']);
    }

    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}
