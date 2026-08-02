---
id: T-00005
prd: PRD-00003
title: Define stored-event and Event Store contracts
status: done
blocked_by: T-00002,T-00003
---

# Define Stored-Event and Event Store Contracts

## What to Build

Define the framework-free storage boundary and immutable envelope needed by every Event Store implementation. The contract makes stream concurrency, exact retry behavior, stable identity, ordered reads, and checkpoint-safe global visibility explicit before adapters are built.

## Blocked By

- T-00002 — Establish Event Sourcing context and decisions.
- T-00003 — Isolate metadata across message envelopes.

## Acceptance

- [x] `StoredEvent` exposes stable aggregate and event identity, original schema version, stream version, global position, and the current hydrated `EventMessage`.
- [x] Event Store append accepts one ordered message batch with an expected version.
- [x] Stream reads and bounded global polling preserve their defined order.
- [x] Exact retries succeed only when every message ID already occupies the intended stream positions.
- [x] Partial, misplaced, reordered, or cross-stream matches fail closed.
- [x] Empty streams have version zero, optimistic concurrency has a dedicated failure type, and missing streams read as empty.
- [x] Contracts remain framework-free and define prefix-stable global visibility for checkpoint consumers.
- [x] Public contract behavior has complete coverage independent of a database adapter.

## Outcome

Added `StreamId`, the immutable `StoredEvent` envelope, the framework-free `EventStore` port, and a dedicated
`OptimisticConcurrencyException`. The port defines ordered append and read behavior, exact message-ID retry
semantics without payload or metadata comparison, fail-closed mismatches, empty-stream versioning, bounded
global polling, and prefix-stable visibility while leaving executable store behavior to T-00007.

## Verification

- Rector dry-run: clean.
- PHPStan: clean.
- PHPCS: clean.
- PHPUnit: 2,842 tests and 4,403 assertions passed without warnings.
- Coverage: 7,601/7,601 statements and 1,701/1,701 methods.
- Planning validation: clean.
- Two-axis review: no Standards or Spec findings after four targeted refinements.
