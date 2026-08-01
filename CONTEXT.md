# Project Context

`fight-common` is the shared PHP library for Fight projects. It follows Hexagonal Architecture: Domain is framework-free, Application coordinates domain ports, and Adapter contains infrastructure implementations.

## Event Sourcing vocabulary

- **Aggregate root:** a domain object whose state changes only by applying domain events.
- **Recorded event:** a new event held by an aggregate until it is persisted.
- **Stored event:** an immutable envelope containing an event message, stream version, stable event name, schema version, and global position.
- **Stream:** the ordered history of one aggregate, identified by aggregate type and identifier.
- **Expected version:** the stream version observed during load and checked during append for optimistic concurrency.
- **Projector:** an idempotent consumer that moves stored events into a read model.
- **Checkpoint:** the last global event position successfully handled by one projector.

Event Sourcing is optional and additive to the existing CQRS and event-dispatching APIs. Aggregates record plain `Event` payloads. Repositories create messages and storage envelopes. Projectors consume stored events independently using at-least-once delivery.

## Durable and ephemeral work

Committed planning state lives in `planning/`. Coordinate-build artifacts live under `.runs/<date>-<slug>/`; `.runs/` is gitignored and is never canonical project history. Important results and deviations from a run must be copied back to its ticket.

## Decisions

Architectural decisions for Event Sourcing live in `planning/adr/`. Public integration documentation lives in `docs/event-sourcing.md`.
