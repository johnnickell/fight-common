# Messaging (CQRS)

A full CQRS architecture with commands, queries, and events. Message primitives live in
`Domain\Messaging`, service contracts in `Application\Messaging`, and adapters (sync + async)
in `Adapter\Messaging`. The Symfony Messenger bridge provides async transport, and compiler
passes auto-wire handlers and filters from the DI container.

```
Domain\Messaging
├── Message (interface)
├── BaseMessage (abstract)
├── MessageId / MessageType (enum) / Payload / Meta
├── Command\Command / CommandMessage
├── Query\Query / QueryMessage
└── Event\Event / EventMessage / AllEvents / CommandFailedEvent

Application\Messaging
├── Command\CommandBus / SynchronousCommandBus / AsynchronousCommandBus
│            CommandHandler / CommandFilter
├── Query\QueryBus / QueryHandler / QueryFilter
└── Event\EventDispatcher / SynchronousEventDispatcher / AsynchronousEventDispatcher
                EventSubscriber

Adapter\Messaging
├── Command\Sync\RoutingCommandBus + CommandPipeline
│            Sync\Routing\{CommandRouter, InMemory*, ServiceAware*}
│   Async\MessengerCommandBus
├── Query\RoutingQueryBus + QueryPipeline
│       Query\Routing\{QueryRouter, InMemory*, ServiceAware*}
└── Event\Sync\{SimpleEventDispatcher, ServiceAwareEventDispatcher}
    Event\Async\MessengerEventDispatcher
    Handler\{SymfonyCommandMessageHandler, SymfonyEventMessageHandler}
    Serializer\SymfonyMessageSerializer

Adapter\DependencyInjection
├── CommandHandlerCompilerPass
├── CommandFilterCompilerPass
├── QueryHandlerCompilerPass
├── QueryFilterCompilerPass
└── EventSubscriberCompilerPass
```

---

## Table of Contents

1. [Message Primitives](#message-primitives)
2. [Commands](#commands)
3. [Queries](#queries)
4. [Events](#events)
5. [Pipeline Filters](#pipeline-filters)
6. [Async with Symfony Messenger](#async-with-symfony-messenger)
7. [Compiler Passes](#compiler-passes)
8. [Full Symfony Configuration](#full-symfony-configuration)
9. [Controller Examples](#controller-examples)

---

## Message Primitives

### Message Interface

`Fight\Common\Domain\Messaging\Message`

The root interface for all message envelopes. Extends `Arrayable`, `Comparable`, `Equatable`,
`JsonSerializable`, and `Serializable`.

```php
interface Message extends Arrayable, Comparable, Equatable, JsonSerializable, Serializable
{
    public function id(): MessageId;
    public function type(): MessageType;
    public function timestamp(): DateTimeImmutable;
    public function payload(): Payload;
    public function payloadType(): Type;
    public function meta(): Meta;
    public function withMeta(Meta $data): static;
    public function mergeMeta(Meta $data): static;
    public function toString(): string;
}
```

Equality and comparison are based on the `MessageId` — two messages with the same ID are
considered equal regardless of other fields.

### BaseMessage

`Fight\Common\Domain\Messaging\BaseMessage`

Abstract base implementing `Message`. Stores `id`, `type`, `timestamp`, `payload`, and `meta`.
Serialization produces a uniform envelope:

```php
[
    'id'           => '018abc...',     // MessageId as string
    'type'         => 'command',       // MessageType value
    'timestamp'    => '1712345678',    // Unix timestamp
    'payload_type' => 'RegisterUserCommand',
    'payload'      => ['email' => '...', 'name' => '...'],
    'meta'         => ['trace_id' => 'abc123'],
]
```

### MessageId

`Fight\Common\Domain\Messaging\MessageId`

Extends `UniqueId` — auto-generated UUID identifier for every message envelope.

```php
$id = MessageId::generate();
$id = MessageId::fromString('018abc...');
```

### MessageType

`Fight\Common\Domain\Messaging\MessageType`

A string-backed PHP enum:

```php
enum MessageType: string
{
    case COMMAND = 'command';
    case QUERY   = 'query';
    case EVENT   = 'event';
}
```

### Payload

`Fight\Common\Domain\Messaging\Payload`

Marker interface extended by `Command`, `Query`, and `Event`. Requires `fromArray()` /
`toArray()` — the actual business data.

```php
interface Payload extends Arrayable
{
    public static function fromArray(array $data): static;
    public function toArray(): array;
}
```

### Meta

`Fight\Common\Domain\Messaging\Meta`

Key-value metadata container attached to every message envelope. Accepts only scalars and
arrays of scalars — guards against complex types on `set()`.

```php
$meta = Meta::create(['trace_id' => 'abc', 'user_id' => 42]);

$meta->has('trace_id');     // true
$meta->get('trace_id');     // 'abc'
$meta->set('source', 'cli');
$meta->remove('user_id');
$meta->merge($otherMeta);
$meta->toArray();           // ['trace_id' => 'abc', 'source' => 'cli']
$meta->count();             // 2
```

Implements `Countable`, `IteratorAggregate`, `JsonSerializable`, `Stringable`.

---

## Commands

### Domain Layer

**`Command`** — marker interface extending `Payload`:

```php
namespace Fight\Common\Domain\Messaging\Command;

interface Command extends Payload {}
```

**`CommandMessage`** — envelope wrapping a `Command`:

```php
final class CommandMessage extends BaseMessage
{
    // Wrap a command in a message with auto-generated ID + timestamp
    public static function create(Command $command): static;

    // Deserialize from the envelope array (validates type === 'command')
    public static function arrayDeserialize(array $data): static;
}
```

```php
$command  = new RegisterUserCommand('user@example.com', 'Alice');
$envelope = CommandMessage::create($command);

$envelope->id();              // MessageId
$envelope->type();            // MessageType::COMMAND
$envelope->payload();         // RegisterUserCommand
$envelope->meta();            // Meta (empty by default)
$envelope->withMeta($meta);   // clone with replacement meta
$envelope->mergeMeta($meta);  // clone with merged meta
```

### Application Contracts

**`CommandBus`** — the bus interface. Two dispatch styles:

```php
interface CommandBus
{
    // Wrap + dispatch (convenience)
    public function execute(Command $command): void;

    // Dispatch a pre-built message
    public function dispatch(CommandMessage $commandMessage): void;
}
```

**`SynchronousCommandBus`** / **`AsynchronousCommandBus`** — marker subinterfaces used by
adapter consumers to declare intent.

**`CommandHandler`** — each handler declares which command it handles via a static method:

```php
interface CommandHandler
{
    public static function commandRegistration(): string;
    public function handle(CommandMessage $commandMessage): void;
}
```

```php
class RegisterUserHandler implements CommandHandler
{
    public static function commandRegistration(): string
    {
        return RegisterUserCommand::class;
    }

    public function handle(CommandMessage $commandMessage): void
    {
        /** @var RegisterUserCommand $command */
        $command = $commandMessage->payload();
        // ... business logic
    }
}
```

**`CommandFilter`** — middleware-style pipeline filter:

```php
interface CommandFilter
{
    // $next signature: function (CommandMessage): void
    public function process(CommandMessage $commandMessage, callable $next): void;
}
```

### Sync Adapters

**`CommandRouter`** — locates a handler for a command:

```php
interface CommandRouter
{
    /** @throws LookupException when not found */
    public function match(Command $command): CommandHandler;
}
```

Two implementations:

| Implementation | Storage | Resolution |
|---|---|---|
| `InMemoryCommandRouter` | Direct handler instances | `registerHandler(CommandClass::class, $handlerInstance)` |
| `ServiceAwareCommandRouter` | Service IDs in container | `registerHandler(CommandClass::class, 'service_id')` — lazy-loaded on `match()` |

```php
// InMemory — useful in tests
$router = new InMemoryCommandRouter();
$router->registerHandler(RegisterUserCommand::class, $handler);

// ServiceAware — production with DI
$router = new ServiceAwareCommandRouter($container);
$router->registerHandler(RegisterUserCommand::class, 'app.handler.register_user');
```

**`RoutingCommandBus`** — sync bus that delegates to the router:

```php
final readonly class RoutingCommandBus implements SynchronousCommandBus
{
    public function execute(Command $command): void
    {
        $this->dispatch(CommandMessage::create($command));
    }

    public function dispatch(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        $this->commandRouter->match($command)->handle($commandMessage);
    }
}
```

**`CommandPipeline`** — decorates a `SynchronousCommandBus` with a stack of `CommandFilter`s:

```php
$pipeline = new CommandPipeline($routingCommandBus);
$pipeline->addFilter(new LoggingCommandFilter());
$pipeline->addFilter(new ValidationCommandFilter());

$pipeline->execute($command);  // goes through each filter, then the bus
```

Internally uses a `LinkedStack` of filters. Each filter calls `$next` to pass control to the
next filter in the stack, ending at the inner bus.

### Async Adapter

**`MessengerCommandBus`** — sends commands to a Symfony Messenger transport:

```php
final readonly class MessengerCommandBus implements AsynchronousCommandBus
{
    public function execute(Command $command): void
    {
        $this->dispatch(CommandMessage::create($command));
    }

    public function dispatch(CommandMessage $commandMessage): void
    {
        $this->sender->send(new Envelope($commandMessage));
    }
}
```

```php
// In a controller you use the async bus for commands
class RegisterController
{
    public function __construct(private AsynchronousCommandBus $commandBus) {}

    public function __invoke(Request $request): Response
    {
        $command = new RegisterUserCommand(
            $request->get('email'),
            $request->get('name')
        );

        $this->commandBus->execute($command);

        return new Response('Processing', 202);
    }
}
```

### Example: RegisterUserCommand

```php
use Fight\Common\Domain\Messaging\Command\Command;

final readonly class RegisterUserCommand implements Command
{
    public function __construct(
        private string $email,
        private string $name,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static($data['email'], $data['name']);
    }

    public function toArray(): array
    {
        return ['email' => $this->email, 'name' => $this->name];
    }

    public function email(): string { return $this->email; }
    public function name(): string  { return $this->name; }
}

class RegisterUserHandler implements CommandHandler
{
    public function __construct(
        private UserRepository $users,
        private SynchronousEventDispatcher $events,
    ) {}

    public static function commandRegistration(): string
    {
        return RegisterUserCommand::class;
    }

    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        $user = User::register($command->email(), $command->name());
        $this->users->save($user);

        $this->events->trigger(new UserRegisteredEvent($user->id()));
    }
}
```

---

## Queries

Queries are always synchronous — there is no async query bus. The pattern mirrors commands.

### Domain Layer

```php
namespace Fight\Common\Domain\Messaging\Query;

interface Query extends Payload {}
```

**`QueryMessage`** — envelope wrapping a `Query`. Same structure as `CommandMessage` with
`type === 'query'`.

```php
$query    = new GetUserQuery('018abc...');
$envelope = QueryMessage::create($query);
```

### Application Contracts

```php
interface QueryBus
{
    public function fetch(Query $query): mixed;
    public function dispatch(QueryMessage $queryMessage): mixed;
}

interface QueryHandler
{
    public static function queryRegistration(): string;
    public function handle(QueryMessage $queryMessage): mixed;
}

interface QueryFilter
{
    public function process(QueryMessage $queryMessage, callable $next): void;
}
```

### Adapters

**`QueryRouter`** (with `InMemoryQueryRouter` / `ServiceAwareQueryRouter`) — same pattern as commands.

**`RoutingQueryBus`** — sync-only bus:

```php
final readonly class RoutingQueryBus implements QueryBus
{
    public function fetch(Query $query): mixed
    {
        return $this->dispatch(QueryMessage::create($query));
    }

    public function dispatch(QueryMessage $queryMessage): mixed
    {
        $query = $queryMessage->payload();
        return $this->queryRouter->match($query)->handle($queryMessage);
    }
}
```

**`QueryPipeline`** — decorates `QueryBus` with filter stack (same pipeline pattern as commands).

### Example: GetUserQuery

```php
final readonly class GetUserQuery implements Query
{
    public function __construct(private string $userId) {}

    public static function fromArray(array $data): static
    {
        return new static($data['user_id']);
    }

    public function toArray(): array
    {
        return ['user_id' => $this->userId];
    }

    public function userId(): string { return $this->userId; }
}

class GetUserHandler implements QueryHandler
{
    public function __construct(private UserRepository $users) {}

    public static function queryRegistration(): string
    {
        return GetUserQuery::class;
    }

    public function handle(QueryMessage $queryMessage): mixed
    {
        $query = $queryMessage->payload();
        return $this->users->find($query->userId());
    }
}
```

---

## Events

### Domain Layer

**`Event`** — marker interface extending `Payload`:

```php
namespace Fight\Common\Domain\Messaging\Event;

interface Event extends Payload {}
```

**`EventMessage`** — envelope wrapping an `Event`. Same structure as `CommandMessage`/`QueryMessage`
with `type === 'event'`.

```php
$event    = new UserRegisteredEvent($userId);
$envelope = EventMessage::create($event);
```

**`AllEvents`** — marker class. Event subscribers can use this instead of a specific event
class to register for every event.

```php
final class AllEvents
{
    // No methods — marker only
}
```

**`CommandFailedEvent`** — a built-in event payload emitted when a command fails. Contains
the original `Command` and error message:

```php
final readonly class CommandFailedEvent implements Event
{
    public function __construct(
        private readonly Command $command,
        private readonly string $errorMessage,
    ) {}

    public function getCommand(): Command;
    public function getErrorMessage(): string;
}
```

### Application Contracts

```php
interface EventDispatcher
{
    // Wrap + dispatch
    public function trigger(Event $event): void;

    // Dispatch a pre-built message
    public function dispatch(EventMessage $eventMessage): void;

    // Subscriber management
    public function register(EventSubscriber $subscriber): void;
    public function unregister(EventSubscriber $subscriber): void;

    // Fine-grained handler control
    public function addHandler(string $eventType, callable $handler, int $priority = 0): void;
    public function getHandlers(?string $eventType = null): array;
    public function hasHandlers(?string $eventType = null): bool;
    public function removeHandler(string $eventType, callable $handler): void;
}
```

`SynchronousEventDispatcher` and `AsynchronousEventDispatcher` are marker subinterfaces.

**`EventSubscriber`** — declarative registration. The static method returns a map of event
class → handler method, with optional priority:

```php
interface EventSubscriber
{
    // Returns: [EventClass::class => 'methodName']
    // Or:     [EventClass::class => ['methodName', priority]]
    // Or:     [EventClass::class => [['methodOne', 10], ['methodTwo']]]
    // Use AllEvents::class to subscribe to everything
    public static function eventRegistration(): array;
}
```

### Sync Adapters

**`SimpleEventDispatcher`** — the base implementation. Handlers are stored in-memory by event
type, sorted by priority (highest first). `dispatch()` calls handlers for the specific event
type, then handlers registered for `AllEvents`.

```php
$dispatcher = new SimpleEventDispatcher();
$dispatcher->register($subscriber);
$dispatcher->addHandler(UserRegisteredEvent::class, $callable, 10);
$dispatcher->trigger(new UserRegisteredEvent($userId));
```

**`ServiceAwareEventDispatcher`** — extends `SimpleEventDispatcher`. Accepts service IDs
instead of concrete instances. Lazy-loads handlers from the container on first dispatch:

```php
$dispatcher = new ServiceAwareEventDispatcher($container);
$dispatcher->registerService(UserRegisteredEvent::class, 'app.subscriber.send_welcome_email');

// On dispatch, loads 'app.subscriber.send_welcome_email' from container
$dispatcher->trigger(new UserRegisteredEvent($userId));
```

### Async Adapter

**`MessengerEventDispatcher`** — sends event messages to a Messenger transport. All
`register()` / `addHandler()` / etc. are no-ops — the dispatcher only serializes and sends.

```php
final readonly class MessengerEventDispatcher implements AsynchronousEventDispatcher
{
    public function trigger(Event $event): void
    {
        $this->sender->send(new Envelope(EventMessage::create($event)));
    }
}
```

### Example: UserRegisteredEvent + Subscriber

```php
final readonly class UserRegisteredEvent implements Event
{
    public function __construct(private string $userId) {}

    public static function fromArray(array $data): static
    {
        return new static($data['user_id']);
    }

    public function toArray(): array
    {
        return ['user_id' => $this->userId];
    }

    public function userId(): string { return $this->userId; }
}

class SendWelcomeEmailSubscriber implements EventSubscriber
{
    public function __construct(private Mailer $mailer) {}

    public static function eventRegistration(): array
    {
        return [UserRegisteredEvent::class => 'onUserRegistered'];
    }

    public function onUserRegistered(EventMessage $message): void
    {
        /** @var UserRegisteredEvent $event */
        $event = $message->payload();
        $this->mailer->sendWelcome($event->userId());
    }
}
```

---

## Pipeline Filters

Both commands and queries support a pipeline/filter stack. Filters implement the same
interface and are stacked via `LinkedStack`.

### Creating a Filter

```php
use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Domain\Messaging\Command\CommandMessage;

class LoggingCommandFilter implements CommandFilter
{
    public function __construct(private LoggerInterface $logger) {}

    public function process(CommandMessage $commandMessage, callable $next): void
    {
        $command = $commandMessage->payload();
        $this->logger->info('Before: ' . $command::class);

        $next($commandMessage);

        $this->logger->info('After: ' . $command::class);
    }
}
```

```php
use Fight\Common\Application\Messaging\Query\QueryFilter;
use Fight\Common\Domain\Messaging\Query\QueryMessage;

class LoggingQueryFilter implements QueryFilter
{
    // same pattern as above, but for queries
}
```

### Wiring a Pipeline

```php
use Fight\Common\Adapter\Messaging\Command\Sync\CommandPipeline;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;

$bus     = new RoutingCommandBus($router);
$pipeline = new CommandPipeline($bus);

$pipeline->addFilter(new LoggingCommandFilter());
$pipeline->addFilter(new ValidationCommandFilter());

$pipeline->execute($command);
```

With Symfony DI, filters are auto-wired via the `CommandFilterCompilerPass` / `QueryFilterCompilerPass`
— just tag the service.

---

## Async with Symfony Messenger

The async path sends `CommandMessage` / `EventMessage` envelopes through Symfony Messenger
transports. On the consuming side, message handlers receive the envelope and forward it to
the sync bus/dispatcher.

### Sender Side

| Bus | Sends |
|---|---|
| `MessengerCommandBus` | `CommandMessage` → transport via `SenderInterface` |
| `MessengerEventDispatcher` | `EventMessage` → transport via `SenderInterface` |

### Receiver Side (Consuming from Transport)

**`SymfonyCommandMessageHandler`** — an invocable Messenger handler that receives
`CommandMessage` from the transport and forwards it to the sync `SynchronousCommandBus`:

```php
final readonly class SymfonyCommandMessageHandler
{
    public function __construct(private SynchronousCommandBus $commandBus) {}

    public function __invoke(CommandMessage $commandMessage): void
    {
        $this->commandBus->dispatch($commandMessage);
    }
}
```

**`SymfonyEventMessageHandler`** — receives `EventMessage` from transport and forwards
to the sync `SynchronousEventDispatcher`:

```php
final readonly class SymfonyEventMessageHandler
{
    public function __construct(private SynchronousEventDispatcher $eventDispatcher) {}

    public function __invoke(EventMessage $eventMessage): void
    {
        $this->eventDispatcher->dispatch($eventMessage);
    }
}
```

The message handlers **must** be tagged `messenger.message_handler` in Symfony config.

### Serialization

**`SymfonyMessageSerializer`** — implements Messenger's `SerializerInterface`. Uses the
domain `JsonSerializer` (or `PhpSerializer`) to serialize/deserialize messages, and encodes
Messenger stamps in `X-Message-Stamp-*` headers.

```php
final readonly class SymfonyMessageSerializer implements SerializerInterface
{
    public function __construct(private DomainSerializer $serializer) {}

    public function decode(array $encodedEnvelope): Envelope;
    public function encode(Envelope $envelope): array;
}
```

The transport routing in `framework:messenger` must route `CommandMessage` and
`EventMessage` to their respective transports:

```yaml
framework:
    messenger:
        transports:
            commands: '%env(MESSENGER_TRANSPORT_DSN)%'
            events:   '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            'Fight\Common\Domain\Messaging\Command\CommandMessage': commands
            'Fight\Common\Domain\Messaging\Event\EventMessage': events
```

---

## Compiler Passes

Five compiler passes automate wiring through Symfony DI tags. All tagged services **must be
public** because handlers are lazy-loaded on first match/dispatch.

| Tag | Pass | Action |
|---|---|---|
| `common.command_handler` | `CommandHandlerCompilerPass` | Calls `ServiceAwareCommandRouter::registerHandler($commandClass, $serviceId)` |
| `common.command_filter` | `CommandFilterCompilerPass` | Calls `CommandPipeline::addFilter(Reference)` |
| `common.query_handler` | `QueryHandlerCompilerPass` | Calls `ServiceAwareQueryRouter::registerHandler($queryClass, $serviceId)` |
| `common.query_filter` | `QueryFilterCompilerPass` | Calls `QueryPipeline::addFilter(Reference)` |
| `common.event_subscriber` | `EventSubscriberCompilerPass` | Calls `ServiceAwareEventDispatcher::registerService($className, $serviceId)` |

Each pass validates that the tagged service implements the expected interface and throws an
`Exception` if the router/pipeline/dispatcher service is missing or the interface check fails.

### Wiring in the Kernel

The cleanest approach is to use `registerForAutoconfiguration` in your `Kernel::build()` so
that any service implementing the handler/filter/subscriber interface is automatically tagged:

```php
use Fight\Common\Adapter\DependencyInjection\CommandFilterCompilerPass;
use Fight\Common\Adapter\DependencyInjection\CommandHandlerCompilerPass;
use Fight\Common\Adapter\DependencyInjection\EventSubscriberCompilerPass;
use Fight\Common\Adapter\DependencyInjection\QueryFilterCompilerPass;
use Fight\Common\Adapter\DependencyInjection\QueryHandlerCompilerPass;
use Fight\Common\Adapter\DependencyInjection\TemplateHelperCompilerPass;
use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Application\Messaging\Query\QueryFilter;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Application\Templating\TemplateHelper;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    #[Override]
    protected function build(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(CommandHandler::class)->addTag('common.command_handler');
        $container->registerForAutoconfiguration(CommandFilter::class)->addTag('common.command_filter');
        $container->registerForAutoconfiguration(EventSubscriber::class)->addTag('common.event_subscriber');
        $container->registerForAutoconfiguration(QueryHandler::class)->addTag('common.query_handler');
        $container->registerForAutoconfiguration(QueryFilter::class)->addTag('common.query_filter');
        $container->registerForAutoconfiguration(TemplateHelper::class)->addTag('common.template_helper');

        $container->addCompilerPass(new CommandHandlerCompilerPass());
        $container->addCompilerPass(new CommandFilterCompilerPass());
        $container->addCompilerPass(new EventSubscriberCompilerPass());
        $container->addCompilerPass(new QueryHandlerCompilerPass());
        $container->addCompilerPass(new QueryFilterCompilerPass());
        $container->addCompilerPass(new TemplateHelperCompilerPass());
    }
}
```

With this approach, any service that implements, say, `CommandHandler` automatically receives
the `common.command_handler` tag, and the compiler pass wires it into the router. No manual
tagging is needed in `services.yaml`.

---

## Full Symfony Configuration

```yaml
# config/packages/common_messaging.yaml

services:
    _defaults:
        autowire: true
        autoconfigure: true

    # --- Command bus stack ---

    # Sync routing bus
    Fight\Common\Adapter\Messaging\Command\Sync\Routing\RoutingCommandBus:
        class: Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus
        arguments:
            - '@Fight\Common\Adapter\Messaging\Command\Sync\Routing\ServiceAwareCommandRouter'

    # Sync pipeline (decorates routing bus with filters)
    Fight\Common\Adapter\Messaging\Command\Sync\CommandPipeline:
        arguments:
            - '@Fight\Common\Adapter\Messaging\Command\Sync\Routing\RoutingCommandBus'

    # Async command bus (sends to Messenger transport)
    Fight\Common\Adapter\Messaging\Command\Async\MessengerCommandBus:
        arguments:
            - '@messenger.transport.commands'

    # --- Query bus (sync only) ---

    Fight\Common\Adapter\Messaging\Query\RoutingQueryBus:
        arguments:
            - '@Fight\Common\Adapter\Messaging\Query\Routing\ServiceAwareQueryRouter'

    # --- Event dispatchers ---

    Fight\Common\Adapter\Messaging\Event\Sync\ServiceAwareEventDispatcher:
        arguments:
            - '@service_container'

    Fight\Common\Adapter\Messaging\Event\Async\MessengerEventDispatcher:
        arguments:
            - '@messenger.transport.events'

    # --- Bridges: transport → sync ---

    Fight\Common\Adapter\Messaging\Handler\SymfonyCommandMessageHandler:
        arguments:
            - '@Fight\Common\Adapter\Messaging\Command\Sync\CommandPipeline'
        tags:
            - { name: messenger.message_handler }

    Fight\Common\Adapter\Messaging\Handler\SymfonyEventMessageHandler:
        arguments:
            - '@Fight\Common\Adapter\Messaging\Event\Sync\ServiceAwareEventDispatcher'
        tags:
            - { name: messenger.message_handler }

    # --- Message serializer ---

    Fight\Common\Adapter\Messaging\Serializer\SymfonyMessageSerializer:
        arguments:
            - '@Fight\Common\Domain\Serialization\JsonSerializer'

    # --- Event subscriber (sync, auto-registered) ---

    App\Messaging\SendWelcomeEmailSubscriber:
        tags:
            - { name: common.event_subscriber }

    # --- Command handler (auto-registered) ---

    App\Messaging\RegisterUserHandler:
        tags:
            - { name: common.command_handler }

    # --- Query handler (auto-registered) ---

    App\Messaging\GetUserHandler:
        tags:
            - { name: common.query_handler }

    # --- Filters (auto-registered into pipeline) ---

    App\Messaging\LoggingCommandFilter:
        tags:
            - { name: common.command_filter }

    App\Messaging\LoggingQueryFilter:
        tags:
            - { name: common.query_filter }

# --- Messenger transport routing ---

framework:
    messenger:
        transports:
            commands: '%env(MESSENGER_TRANSPORT_DSN)%'
            events:   '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            'Fight\Common\Domain\Messaging\Command\CommandMessage': commands
            'Fight\Common\Domain\Messaging\Event\EventMessage': events
```

You should alias the bus/dispatcher interfaces to the appropriate implementations so
controllers can type-hide against the interface:

```yaml
services:
    Fight\Common\Application\Messaging\Command\AsynchronousCommandBus:
        alias: Fight\Common\Adapter\Messaging\Command\Async\MessengerCommandBus

    Fight\Common\Application\Messaging\Query\QueryBus:
        alias: Fight\Common\Adapter\Messaging\Query\RoutingQueryBus

    Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher:
        alias: Fight\Common\Adapter\Messaging\Event\Sync\ServiceAwareEventDispatcher
```

### Data Flow Summary

```
Controller (async command bus)
  └── MessengerCommandBus::execute($command)
        └── SenderInterface::send(Envelope(CommandMessage))
              │
              ▼  (transport delivers to consumer)
        SymfonyCommandMessageHandler::__invoke($commandMessage)
              └── CommandPipeline::dispatch($commandMessage)
                    └── filters...
                          └── RoutingCommandBus::dispatch($commandMessage)
                                └── CommandRouter::match($command)
                                      └── CommandHandler::handle($commandMessage)

Controller (query bus)
  └── RoutingQueryBus::fetch($query)
        └── QueryRouter::match($query)
              └── QueryHandler::handle($queryMessage)

Controller (event dispatcher)
  └── ServiceAwareEventDispatcher::trigger($event)
        └── EventMessage::create($event)
              └── handlers for event type (lazy-loaded from container)
                    └── event subscribers + added handlers
```

---

## Controller Examples

### Command Controller (Async — HTTP 202)

```php
use Fight\Common\Application\Messaging\Command\AsynchronousCommandBus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RegisterUserController
{
    public function __construct(
        private AsynchronousCommandBus $commandBus,
    ) {}

    public function __invoke(Request $request): Response
    {
        $command = new RegisterUserCommand(
            $request->get('email'),
            $request->get('name'),
        );

        $this->commandBus->execute($command);

        return new JsonResponse(['status' => 'accepted'], Response::HTTP_ACCEPTED);
    }
}
```

### Query Controller (Sync — HTTP 200)

```php
use Fight\Common\Application\Messaging\Query\QueryBus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetUserController
{
    public function __construct(private QueryBus $queryBus) {}

    public function __invoke(Request $request): Response
    {
        $query  = new GetUserQuery($request->get('id'));
        $result = $this->queryBus->fetch($query);

        if ($result === null) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($result->toArray());
    }
}
```

### Event Dispatch in a Service

```php
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;

class RegisterUserHandler implements CommandHandler
{
    public function __construct(
        private UserRepository $users,
        private SynchronousEventDispatcher $events,
    ) {}

    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        $user    = User::register($command->email(), $command->name());
        $this->users->save($user);

        // In async setups, use AsynchronousEventDispatcher here instead
        $this->events->trigger(new UserRegisteredEvent($user->id()));
    }
}
```
