# Event Sourcing

Fight Common provides portable contracts for event-sourced aggregates and event
storage. The framework-free baseline is explicit construction: the consuming
application owns its domain events and aggregate, registers durable names with
an `EventMapper`, and composes an `EventStore` with an
`EventSourcedRepository`.

## Upgrade compatibility

Event Sourcing is additive. Existing CQRS event triggering and dispatch remain
supported without adopting Event Store mappings. Only events stored in the
Event Store need mapper registration; dispatch-only events do not.

Metadata getter isolation is a signature-compatible behavioral change. Message
method signatures remain unchanged, but callers receive metadata copies from
`meta()` and derived same-ID envelopes from `withMeta()` or `mergeMeta()`. Code
that previously mutated the value returned by `meta()` and expected the original
message to change must instead retain and use the envelope returned by
`withMeta()` or `mergeMeta()`.

## Persist and reload an aggregate

An aggregate extends `AggregateRoot`, records new events, replays stored events
when it is reconstituted, and routes every supported event explicitly. Event
payloads implement `Event`, including the `toArray()` and `fromArray()` methods
inherited from the payload contract.

The following example defines an order without depending on a framework:

The aggregate persistence path, one bounded projection poll, and one bounded
publication poll form an executable example in the repository documentation
tests. They run against an in-memory SQLite database through the same public
contracts shown below, so signature or behavior drift in that bounded journey
fails the documentation suite. The remaining operational and Symfony snippets
come from named regions in that same parsed and statically analyzed fixture.

```php
--8<-- "tests/Documentation/EventSourcingGuideExample.php:aggregate-types"
```

The mapping namespace and local names combine into the stable event names
`orders.placed` and `orders.renamed`. Keep those storage identities stable even
if the PHP event classes move. Likewise, `orders` below is the stable aggregate name
used in stream identity; it is deliberately separate from `Order::class`.

Install the reference DBAL event-store schema once for the connection, then
construct the mapper, store, and repository directly:

```php
--8<-- "tests/Documentation/EventSourcingGuideExample.php:event-store-composition"
```

Saving releases the aggregate's pending events and appends them with the
aggregate's previous version as the optimistic-concurrency expectation.
`find()` reads the ordered stream, gives the aggregate plain event payloads for
replay, and returns `null` when no stream exists:

```php
--8<-- "tests/Documentation/EventSourcingGuideExample.php:persist-reload"
```

After a successful save, the released batch is no longer pending on that
instance. Reconstitution replays history without recording it again, so the
reloaded aggregate has the restored version and no pending events.

An append is an exact retry only when the same message IDs occupy the intended
consecutive stream positions immediately after the supplied expected version.
That retry succeeds without writing again or comparing payload or metadata
content. If any ID is partial, misplaced, reordered, or owned by another stream,
the append fails closed. A stale expected version also fails closed when none of
the requested IDs establish an exact retry.

Both stream and global reads use the store's complete `EventMapper`. They retain
the persisted schema version, apply the complete sequential upcaster chain in
memory, and hydrate the current `EventMessage` payload class without rewriting
history. An unknown stable event name, unsupported or newer schema version, or
invalid or incomplete mapping raises `EventMappingException`; reads never guess,
skip, downcast, or best-effort hydrate stored history.

## Migrate durable names and event schemas

Choose durable names before writing the first production event. The stable
aggregate name identifies streams, stable event names identify stored payloads,
the stable projector name identifies its checkpoint, and the stable publication
name identifies its cursor and failure records. Treat all four as storage or
operational identities rather than PHP class names or display labels.

A PHP aggregate class refactor updates the current class in its
`AggregateDefinition`. A PHP event class refactor updates the current class in
its existing `EventMapping`. A projector or publication worker class refactor
keeps its existing configured name. When the durable identity and stored
payload schema have not changed, the class refactor
does not require an alias change or an upcaster.

A payload schema change is different. Increment the mapping's current schema
version and provide one sequential upcaster per version: version 1 to 2, then 2
to 3, and so on. Upcasters transform stored payload data in memory before the
current event class is hydrated; they do not rewrite history. Keep the stable
event name unchanged. Missing, duplicate, or skipping steps fail mapper
registration rather than allowing a partial migration.

Message envelopes isolate metadata at their boundary. Construction copies the
provided `Meta`, `meta()` returns a copy, and `withMeta()` or `mergeMeta()`
creates a same-ID envelope with a derived snapshot. Build the final envelope
before append. One successful append persists one metadata snapshot and
preserves it in append-only history; later mutations of a separate `Meta` value
or later derived envelopes do not change the stored record.

Repository save releases pending events before it calls `EventStore::append()`.
If that append fails, discard the released aggregate instance: do not retry
`save()` or make further decisions with it. Resolve the failure, then
load a fresh aggregate from the repository and repeat the command decision
against its current state. Event Store adapters may perform safe internal transient retries
while they still own the released messages.

When a migration changes read-model logic or adds an event class to
`eventClasses()`, rebuild that model using the projection sequence already
documented below: Stop the projection worker. Clear or recreate the read model.
Run `$checkpointStore->reset('orders.order-summary');`. Restart the projection
worker. Do not apply that checkpoint procedure to publication cursors, which
have no reset operation in 1.2.

## Optionally collect mapping providers with Symfony

Manual provider construction is the portable baseline, and
manual construction remains supported with or without Symfony:

```php
--8<-- "tests/Documentation/EventSourcingGuideExample.php:manual-mapper"
```

Applications using Symfony's DependencyInjection component may instead opt in
to `EventMappingProviderCompilerPass`. Register autoconfiguration for the
portable provider interface, define an initially empty mapper, and add the
compiler pass:

```php
--8<-- "tests/Documentation/EventSourcingGuideExample.php:symfony-container"
```

The configured `EventMapper` should be referenced by the application's
`EventStore` definition (or another consuming service), just as in the manual
composition. It does not need to be fetched directly from the container.

Autoconfiguration adds the `common.event_mapping_provider` tag to each service
implementing `EventMappingProvider`. During compilation, the compiler pass
collects those tagged service IDs and adds provider references to the configured
mapper definition, equivalent to this method-call wiring for each provider:

```php
--8<-- "tests/Documentation/EventSourcingGuideExample.php:symfony-provider-reference"
```

Providers may remain private services and may themselves be dependency-injected;
the compiler pass neither instantiates nor reflects over them. Symfony resolves
the provider references and calls `EventMapper::registerProvider()`
when the mapper is resolved,
so the portable mapper validation remains authoritative.
Duplicate aliases, duplicate event classes, invalid durable names, and invalid
upcaster chains therefore raise `EventMappingException` through the same path as
manual registration. The mapper needs to be public only if application code
fetches it directly from the container; normal private dependency injection does
not require that.

## Project and rebuild read models

Run projections in a worker that is separate from the command or request that
appends events. A `Projector` owns one stable name, declares the current event
payload classes it handles, and performs an idempotent read-model update for
each delivered `StoredEvent`.

The read-model storage remains application-owned. For example, this writer's
`upsertIfNewer()` operation must atomically update an order only when the
supplied global position is newer than the position already stored. Repeating
the same event therefore has no further effect:

```php
--8<-- "tests/Documentation/EventSourcingGuideExample.php:projection-types"
```

Keep `orders.order-summary` stable across PHP class refactors because it is the
durable checkpoint identity. `eventClasses()` contains the current, hydrated
payload classes from the `EventMapper`, not stored event aliases.

Install the checkpoint schema independently from the event-store schema as an
application deployment step, then compose the public worker contracts:

```php
--8<-- "tests/Documentation/EventSourcingGuideExample.php:projection-composition"
```

One poll reads a bounded batch. A long-running worker can repeat that bounded
operation and back off when the named checkpoint does not advance:

```php
--8<-- "tests/Documentation/EventSourcingGuideExample.php:projection-worker"
```

`ProjectionRunner` loads the named checkpoint and asks the `EventStore` for at
most the supplied limit strictly after that global position. It processes the
committed events in ascending global order. An event whose current payload
class is not declared by the projector is a successful skip: the projector is
not called, but that event's position is checkpointed so polling can continue.

Global polling is prefix-stable: once position N is visible, no event at a lower
position can become visible later. Durable stores serialize global-position
allocation inside the append transaction. MySQL and PostgreSQL hold a
transactional sequence-row lock through commit; SQLite relies on serialized
writer behavior. On MySQL, an event-table auto-increment key is not a safe
substitute because allocation order does not guarantee commit order.

For a declared event, `project()` runs before the checkpoint advances. If the
read-model update throws, the exception stops the batch, the failed position is
not checkpointed, and no later position in that batch is attempted. The next
poll starts strictly after the last successful checkpoint and retries the
failed event.

Projection delivery is at-least-once, not exactly-once. If the read-model write
succeeds and the worker crashes before the checkpoint advances, or checkpoint
storage itself fails, the same `StoredEvent` is delivered again. Make every
`project()` operation idempotent with an atomic conditional update such as the
global-position guard above, or with an application-owned event-message
deduplication record committed with the read-model write.

To rebuild one read model from all available history, perform these
administrative steps in order:

1. Stop the projection worker.
2. Clear or recreate the read model.
3. Reset only its stable named checkpoint:

   ```php
   --8<-- "tests/Documentation/EventSourcingGuideExample.php:projection-reset"
   ```

4. Restart the projection worker.

`reset()` is an idempotent administrative operation that returns the named
checkpoint to zero. Stopping the worker first prevents a concurrent poll from
advancing the checkpoint while the read model is being replaced. Other named
projectors and their checkpoints are unaffected.

## Publish and diagnose committed events

Publish committed stored events from a worker that is separate from command
handling and from projection workers. Give each subscriber pipeline an
explicit stable publication name. Its `PublicationCursorStore` and failure
records use that name as durable identity, so independent pipelines can read
the same `EventStore` without sharing progress.

Install the cursor and failure schemas independently as deployment steps. The
logging recorder is composable: it logs a portable failure snapshot first,
then delegates the same snapshot to the durable DBAL recorder.

```php
--8<-- "tests/Documentation/EventSourcingGuideExample.php:publication-composition"
```

Here, `$eventStore` is the same store used by the repository, and
`$eventDispatcher` is an already configured `SynchronousEventDispatcher` such
as `SimpleEventDispatcher` or `ServiceAwareEventDispatcher`. An asynchronous
dispatcher cannot provide the completed subscriber-execution boundary this
runner requires.

A worker can invoke one bounded poll at a time:

```php
--8<-- "tests/Documentation/EventSourcingGuideExample.php:publication-run"
```

For each stored event, synchronous dispatch invokes event-specific handlers in
descending priority order, followed by `AllEvents` handlers in descending
priority order. If a subscriber throws, dispatch
continues through every handler in both phases. Once fan-out is complete, all
handler failures are reported in invocation order by one `EventDispatchFailed`
exception.

The runner catches only that completed-fan-out failure, converts it to one
portable `EventPublicationFailure`, records it, and advances the publication
cursor after the failure is recorded. A subscriber failure therefore does not
automatically retry that event. An exception raised while resolving or
preparing handlers is not proof of completed fan-out; it propagates without
cursor advancement.

Delivery can still be duplicated. If the process crashes after successful
dispatch, or after failure recording, but before the cursor is saved, the next
poll dispatches the stored event again. A cursor-store failure has the same
effect. Subscribers should therefore tolerate duplicate delivery when their
side effects require it. Unlike a projection checkpoint, a publication cursor
has no reset operation in 1.2 because subscribers are not required to be
idempotent.

The logging decorator logs before delegation.
A logger or recorder infrastructure failure propagates and blocks cursor
advancement; a retry can therefore produce a duplicate log. The DBAL recorder
is idempotent by stable publication name and global position and retains the
first evidence for that key. It is a durable operational record.
It has no public query API in Fight Common 1.2.

Failure evidence includes handler identity, exception class and code, and a
control-safe, valid UTF-8 diagnostic limited to 4 KiB. Apart from that
normalization and bound, consumer exception messages are persisted and logged
verbatim: Fight Common does not detect or redact secrets. Make every exception
message safe to persist and log. Payloads, message metadata, stack traces, and
the original throwable are not retained in the portable record.

Fight Common 1.2 provides no automatic or targeted replay of publication
failures. Whole-stream or failed-handler replay, including any interpretation
of historical handler identities after refactors, remains application-owned.
