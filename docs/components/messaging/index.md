---
template: atlas-article.html
atlas_article: true
title: Messaging (CQRS)
atlas_article_heading_id: messaging-cqrs
atlas_component_group: Coordinate Application Behavior
atlas_component_owner: Domain, Application, and Adapter
atlas_component_dependencies: PHP 8.5+, optional Symfony Messenger, Laravel Queue, CodeIgniter Queue, and Symfony DependencyInjection
atlas_article_context: Domain · Application · Adapter
atlas_article_lead: Coordinate commands, queries, and events through portable envelopes and ports, then choose synchronous or supported asynchronous adapters at the boundary.
atlas_article_requires: PHP 8.5+
atlas_article_optional: symfony/messenger, laravel/framework, codeigniter4/queue, symfony/dependency-injection
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Queue consumer
atlas_relationship_source: CommandMessageHandler
atlas_relationship_target_label: Application port
atlas_relationship_target: SynchronousCommandBus
atlas_relationship_description: Queue consumers forward complete command envelopes to the synchronous application bus
atlas_relationship_caption: Transport adapters own submission and delivery; the handler re-enters the portable synchronous command path.
atlas_consequential_label: Consequential behavior
atlas_consequential_message: Queue submission is not proof of handling; commands and events may be delivered again, so handlers and side effects must be idempotent.
atlas_next_steps:
  - label: Persist aggregate history
    href: "../event-sourcing/"
  - label: Choose transaction boundaries
    href: "../repositories/"
  - label: Validate at application boundaries
    href: "../validation/"
  - label: Render and deliver mail
    href: "../mail/"
  - label: Configure framework support
    href: "../../frameworks/framework-support/"
atlas_local_contents:
  - label: Message primitives
    href: "#message-primitives"
  - label: Commands
    href: "#commands"
  - label: Queries
    href: "#queries"
  - label: Events
    href: "#events"
  - label: Pipeline filters
    href: "#pipeline-filters"
  - label: Async delivery
    href: "#async-delivery"
  - label: Symfony Messenger
    href: "#async-with-symfony-messenger"
  - label: Laravel Queue
    href: "#laravel-queue-capability-provider"
  - label: CodeIgniter Queue
    href: "#codeigniter-queue-capability-delegation"
---

Messaging gives a use case one vocabulary for a command that changes state, a query that returns
state, and an event that reports a completed fact. Keep payload data and immutable envelopes in the
Domain; let the Application define buses, handlers, filters, and dispatch ports; bind routing,
pipelines, serialization, queue consumers, and framework configuration in Adapter code.

**Ownership.** `Command`, `Query`, `Event`, their message envelopes, `MessageId`, and `Meta` are
Domain primitives. Command/query buses, handlers and filters, event-dispatcher contracts, and
subscribers belong to the Application. Synchronous routers and pipelines, service-aware handlers,
serializers, and framework queue bridges are adapters. Consumers own handler business logic,
transport topology, retries, workers, dead-letter handling, and transaction/outbox policy.

**Dependencies.** The portable synchronous path requires PHP 8.5+ and this package. Symfony
Messenger support is optional via `symfony/messenger`; Laravel asynchronous command and event jobs
require `laravel/framework`; CodeIgniter queue support requires `codeigniter4/queue`; Symfony
autowiring passes require `symfony/dependency-injection`.

**Install.**

```bash
composer require johnnickell/fight-common
```

**Start with a portable synchronous command.**

```php-inline
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Domain\Messaging\Command\CommandMessage;

$router = new InMemoryCommandRouter();
$router->registerHandler(PlaceOrder::class, new PlaceOrderHandler($orders, $events));

$commands = new RoutingCommandBus($router);
$commands->execute(new PlaceOrder($orderId));

// A pre-built envelope preserves its MessageId, timestamp, and metadata.
$commands->dispatch(CommandMessage::create(new PlaceOrder($orderId)));
```

Synchronous command and query handlers propagate their failures to the caller. A synchronous event
dispatcher invokes event-specific handlers and then `AllEvents` handlers, collecting completed
handler failures in `EventDispatchFailed`. Queries have only the synchronous `QueryBus` path; this
package does not provide an asynchronous query bus.

## Async delivery

Only commands and events have supported asynchronous adapters. Symfony Messenger sends complete
`CommandMessage` and `EventMessage` envelopes and consumes them through the framework-neutral
handlers. Laravel queues `QueuedCommandMessage` and `QueuedEventMessage` after a Laravel database
commit; it is not an atomic outbox. CodeIgniter Queue submits complete serialized envelopes to
project-selected jobs and has no portable post-commit guarantee. In every case, queue/broker choice,
retry and backoff, worker lifecycle, and failure storage are application policy.

An asynchronous event dispatcher intentionally has no local subscribers or handlers; delivery
returns to a synchronous dispatcher in the consumer. Repeated delivery retains the message identity
and full envelope, but no adapter promises exactly-once effects. Make externally visible work
idempotent and use an application-owned outbox or event-store design when atomic persistence and
publication are required.

## Reference

--8<-- "docs/messaging.md"
