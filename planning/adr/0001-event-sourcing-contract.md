# ADR 0001: Minimal Optional Event Sourcing Contract

- Status: accepted
- Date: 2026-08-01

## Decision

Fight Common 1.2 adds Event Sourcing without replacing CQRS. Aggregates record plain domain events, explicitly apply them through aggregate-owned routing, and release pending events. Event stores append with an expected version and expose both stream-order and global-order reads.

Repositories depend on a framework-free event-sourced aggregate interface. Fight Common also supplies a reference `AggregateRoot` abstract base that implements version tracking and pending-event lifecycle while requiring consumer aggregates to route and apply their own events explicitly. Consumers may implement the interface without extending the base class.

`releaseEvents()` returns pending events in recording order and clears them immediately. Repository save is a fail-stop boundary: if append fails after release, the aggregate instance must be discarded and any retry must load a fresh aggregate. Optimistic-concurrency failures therefore cannot retry decisions made from stale state, while Event Store adapters may perform safe transient retries internally.

An empty stream and a new aggregate begin at version zero; the first applied event is version one. Aggregate version increments for both replayed and newly recorded events, while replay never adds pending events. For a released batch, the repository derives the append expectation by subtracting the batch size from the aggregate's current version.

Aggregate event routing is fail-closed. Recording or replaying an event without an explicit aggregate-owned route throws a dedicated domain exception. Historical events that no longer alter current state must still have an explicit no-op transition so incomplete history cannot silently produce apparently valid state.

Reconstitution is an aggregate-owned static named constructor. The generic repository receives an event-sourced aggregate class, loads its history, and invokes that contract rather than depending on a public zero-argument constructor or an injected aggregate factory. Consumer aggregates retain control of private construction and initialization.

The reconstitution contract accepts ordered plain `Event` payloads. Repositories unwrap stored envelopes and event messages before invoking it, so aggregates do not depend on stable aliases, schema versions, stream or global positions, message identity, or metadata. Values required to rebuild domain state must therefore be present in event payloads.

The generic event-sourced repository exposes nullable `find(Identifier)`. An empty stream returns `null` without invoking reconstitution. Application services and handlers own the policy and exception used when an aggregate is required but absent; the persistence boundary does not impose that use-case decision.

Streams use a stable aggregate name plus aggregate identifier rather than a PHP aggregate class name. Each generic repository receives an `AggregateDefinition` that pairs its stable name with the current event-sourced aggregate class. No global aggregate registry is required; a class refactor changes the repository definition without changing stored stream identity.

Every event-sourced aggregate exposes its identity through Fight Common's existing `Identifier` contract. Repositories use the identifier's stable string representation when constructing stream identity; the stable aggregate name prevents collisions between aggregate types with equal identifier strings.

The reference `AggregateRoot` stores any consumer-provided domain-specific `Identifier` and implements `id()` centrally. Fight Common does not introduce or require a generic `AggregateId`; consumers retain types such as `OrderId` and `CustomerId`.

Global event position provides prefix-stable commit visibility: once position N is visible, no event at a lower position may become visible later. Durable stores serialize global-position allocation inside the append transaction. MySQL and PostgreSQL lock a transactional sequence row through commit; MySQL does not rely on an event-table auto-increment key, whose allocation order does not guarantee commit order. SQLite's serialized writer behavior must satisfy the same observable contract.

Event publication occurs only after events are committed to the Event Store. A synchronous Event Dispatcher catches failures from individual matching subscribers, continues fan-out in priority order through event-specific and `AllEvents` subscribers, and reports the collected failures after every subscriber has been attempted. This behavior does not apply to downstream consumers of an asynchronous transport.

Stored events use stable aliases and integer schema versions. The stable alias, rather than the PHP event class name carried by the live `EventMessage`, is the canonical identity in durable storage. Event Store adapters do not persist the serialized `EventMessage` as the canonical stored representation. The event mapper resolves an alias to the current PHP class and reconstructs the live message when reading.

A consumer explicitly registers every stable alias, current event class, and schema version with the event mapper. Registration is required only for stored events. Registration is bidirectional and rejects duplicate aliases, duplicate class mappings, invalid schema versions, and classes that do not implement `Event`. Attribute scanning, convention-based discovery, and fallback to the FQCN are excluded.

The portable contract includes typed event mappings and an `EventMappingProvider` that lets each bounded context declare its mappings. Consumers may register mappings or providers directly. Symfony auto-registration of providers is an optional, non-blocking stretch goal for 1.2 rather than part of the portable contract.

Each Event Store uses one complete event mapper assembled from any number of bounded-context providers. Stable aliases are unique across that store, and its mapper must be able to hydrate every event the store contains. Separate Event Stores may use separate mapper services and catalogs.

Each provider declares a durable event namespace and local event names. The mapper qualifies them into canonical stored aliases, such as `orders` plus `order-placed` becoming `orders.order-placed`. The event namespace remains stable across bounded-context display-name and PHP namespace refactors.

Each typed event mapping owns its current PHP class, current schema version, and complete upcaster chain. Schema versions begin at one, and every upcaster advances exactly one integer version. Registration rejects missing, duplicate, or skipping steps and requires the chain to reach the mapping's declared current version; version-one mappings require no upcasters.

A PHP class rename updates the alias mapping without rewriting stored history or invoking an upcaster. A simple ordered upcaster chain transforms one stored payload schema into one newer payload schema. Doctrine DBAL remains optional; in-memory implementations define executable contract behavior.

Upcasting occurs in memory before hydration and never rewrites stored history. A hydrated `StoredEvent` retains the schema version of its persisted record even though its contained `EventMessage` carries the payload transformed to the mapping's current schema. This preserves source provenance while consumers operate on current event classes.

Mapping stored history is fail-closed. Unknown stable event names, stored schemas newer than the registered current version, and older schemas without a complete upcast path raise dedicated mapping failures. Readers do not downcast, skip, or attempt best-effort hydration.

`MessageId` is the idempotency identity of one event occurrence and its payload. Standalone `Meta` values remain mutable technical context, but message envelopes isolate their metadata snapshots: construction copies metadata, `meta()` returns a copy, and `withMeta()` or `mergeMeta()` derives a same-ID envelope. Metadata does not participate in identity. Event Store append treats an exact retry as already successful when every requested message ID occupies the intended stream versions immediately after the supplied expected version. It does not write again or compare payload or metadata content. A partial match, or an ID found in another stream or position, fails closed; if none of the IDs exist and the expected version is stale, append raises optimistic concurrency failure.

The single successful append snapshots the message metadata present at that time. Later in-memory metadata changes, such as publication timing or transport context, do not rewrite the append-only record and do not affect domain reconstitution.

Returning a metadata copy corrects the existing mismatch between the documented immutable-message contract and `BaseMessage::meta()` exposing its internal mutable object. The method signature remains compatible, but the behavioral change requires explicit 1.2 release notes and compatibility coverage; callers must use `withMeta()` or `mergeMeta()` instead of mutating through the getter.

Event Store implementations own the `EventMapper`. Append accepts `EventMessage` instances and maps them to stable event names, current schemas, payload data, and metadata snapshots. Stream and global reads upcast payload data, hydrate current event classes and messages, and return `StoredEvent` envelopes. Aggregate repositories remain unaware of aliases, schemas, and upcasters.

The Event Store preserves the EventMessage creation timestamp, normalized to UTC with microsecond precision across adapters. That timestamp is technical envelope time rather than guaranteed domain occurrence time; domain-significant time belongs in the event payload. Stream and global positions, not timestamps, define ordering.

No EventMessage factory or clock abstraction is added for 1.2. Aggregate repositories use the existing `EventMessage::create()` contract, while exact construction remains available through the public constructor for tests and specialized callers.

## Excluded

Snapshots, sagas, split/merge upcasting, event deletion, multiple stream layouts, and framework-specific worker daemons are outside 1.2.
