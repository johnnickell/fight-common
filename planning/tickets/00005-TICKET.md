---
id: T-00005
prd: PRD-00003
title: Define stored-event and Event Store contracts
status: ready-for-agent
blocked_by: T-00002,T-00003
---

# Define Stored-Event and Event Store Contracts

## What to Build

Define the framework-free storage boundary and immutable envelope needed by every Event Store implementation. The contract makes stream concurrency, exact retry behavior, stable identity, ordered reads, and checkpoint-safe global visibility explicit before adapters are built.

## Blocked By

- T-00002 — Establish Event Sourcing context and decisions.
- T-00003 — Isolate metadata across message envelopes.

## Acceptance

- [ ] `StoredEvent` exposes stable aggregate and event identity, original schema version, stream version, global position, and the current hydrated `EventMessage`.
- [ ] Event Store append accepts one ordered message batch with an expected version.
- [ ] Stream reads and bounded global polling preserve their defined order.
- [ ] Exact retries succeed only when every message ID already occupies the intended stream positions.
- [ ] Partial, misplaced, reordered, or cross-stream matches fail closed.
- [ ] Empty streams have version zero, optimistic concurrency has a dedicated failure type, and missing streams read as empty.
- [ ] Contracts remain framework-free and define prefix-stable global visibility for checkpoint consumers.
- [ ] Public contract behavior has complete coverage independent of a database adapter.
