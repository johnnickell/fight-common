---
template: atlas-article.html
atlas_article: true
title: Event Sourcing
atlas_article_heading_id: event-sourcing
atlas_component_group: Model the Domain
atlas_component_owner: Domain, Application, and Adapter
atlas_component_dependencies: PHP 8.5+, Domain Messaging, optional Doctrine DBAL and Symfony DependencyInjection
atlas_article_context: Domain · Application · Adapter
atlas_article_lead: Record domain decisions as append-only events, then project and publish committed history from independent workers.
atlas_article_requires: PHP 8.5+
atlas_article_optional: doctrine/dbal, symfony/dependency-injection
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Domain aggregate
atlas_relationship_source: EventSourcedRepository
atlas_relationship_target_label: Storage port
atlas_relationship_target: EventStore
atlas_relationship_description: The repository appends aggregate events through the mapped EventStore contract
atlas_relationship_caption: Aggregate decisions stay in the Domain while an adapter owns durable storage and the Application owns projection and publication work.
atlas_consequential_label: Consequential behavior
atlas_consequential_message: Repository save releases pending events before append; on an append failure, discard that aggregate instance and decide again from freshly loaded history.
atlas_next_steps:
  - label: Choose repository boundaries
    href: "../repositories/"
  - label: Coordinate messages
    href: "../messaging/"
  - label: Model event values
    href: "../values/"
  - label: Trace operational failures
    href: "../observability/"
atlas_local_contents:
  - label: Upgrade compatibility
    href: "#upgrade-compatibility"
  - label: Persist and reload
    href: "#persist-and-reload-an-aggregate"
  - label: Migrate names and schemas
    href: "#migrate-durable-names-and-event-schemas"
  - label: Symfony mapping providers
    href: "#optionally-collect-mapping-providers-with-symfony"
  - label: Project read models
    href: "#project-and-rebuild-read-models"
  - label: Publish and diagnose
    href: "#publish-and-diagnose-committed-events"
---

Event sourcing records the decisions that changed an aggregate as durable, ordered Domain events.
Start with an aggregate, stable aggregate and event names, and the framework-free `EventStore`
contract. Let the Application run projections and publication polls separately from the command that
made the decision; select a durable adapter in the composition root.

**Ownership.** Aggregates, stream identities, mappings, and `EventSourcedRepository` are Domain
code. Projection and publication runners plus their checkpoint and cursor ports are Application
code. In-memory, DBAL, logging, and Symfony mapping-provider integration are Adapter code. Your
application owns domain events, read models, subscribers, deployment schemas, and worker lifetime.

**Dependencies.** The portable model requires PHP 8.5+ and the package's Domain Messaging types.
Durable DBAL storage is optional and requires `doctrine/dbal`; automatic provider collection is
optional and requires `symfony/dependency-injection`.

**Install.**

```bash
composer require johnnickell/fight-common
```

**Start with the portable aggregate path.**

```php-inline
use Fight\Common\Domain\EventSourcing\AggregateDefinition;
use Fight\Common\Domain\EventSourcing\EventSourcedRepository;

$orders = new EventSourcedRepository(
    $eventStore,
    new AggregateDefinition('orders', Order::class),
);

$order = Order::place($orderId, 'Original name');
$orders->save($order);

$reloaded = $orders->find($orderId);
```

`EventStore::append()` uses an expected stream version and fails closed with
`OptimisticConcurrencyException` for stale or non-exact retries. Projections and publication can
deliver events at least once: make read-model writes and subscriber effects idempotent. Stable
aggregate, event, projector, and publication names are durable identities, not PHP class names;
mapping or hydration failure raises `EventMappingException` rather than guessing at history.

## Reference

--8<-- "docs/event-sourcing.md"
