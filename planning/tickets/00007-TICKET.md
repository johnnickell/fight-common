---
id: T-00007
prd: PRD-00003
title: Implement the in-memory Event Store
status: done
blocked_by: T-00006
---

# Implement the In-Memory Event Store

## What to Build

Provide an executable in-memory Event Store that consumers can use in tests and that defines the complete observable behavior every durable adapter must match.

## Blocked By

- T-00006 — Implement event mapping and upcasting.

## Acceptance

- [x] Append atomically enforces expected versions, aggregate identity, and exact-batch message-ID idempotency.
- [x] Append snapshots isolated message metadata, the original message timestamp, stable event aliases, and current schema versions through the Event Mapper.
- [x] Stream and global reads preserve order and limits.
- [x] Reads upcast before hydration and retain the original stored schema version in each `StoredEvent`.
- [x] Store conformance tests cover success, empty streams, conflicts, exact retries, partial and misplaced retries, mapping failures, timestamp fidelity, and prefix-stable global visibility.
- [x] The in-memory adapter becomes the executable reference behavior for durable stores.
- [x] All behavior has complete coverage.

## Outcome

Added `InMemoryEventStore` and its raw `InMemoryEventRecord` snapshot so consumers can append mapped event
messages atomically and read current hydrated `StoredEvent` envelopes in stream or global order. Append now
preflights the complete batch for mapping failures, expected-version conflicts, global message-ID reuse,
exact positional retries, and duplicate IDs inside a new batch before mutating state. Adapter-specific seeded
history proves legacy upcasting, persisted-schema provenance, fail-closed mapping, bounded polling, timestamp
and metadata fidelity, aggregate-name isolation, and prefix-stable visibility without expanding the Domain
`EventStore` contract.

The reusable `EventStoreConformanceTestCase` is the executable reference suite for T-00008's DBAL adapter.
The concrete in-memory tests also prove the aggregate record, release, append, read, and aggregate-owned
reconstitution journey required by PRD-00003.

## Verification

- Rector dry-run: clean across 379 files.
- PHPStan: clean across 379 files.
- PHPCS: clean.
- PHPUnit: 2,868 tests and 4,508 assertions passed.
- Coverage: 7,765/7,765 statements and 1,735/1,735 methods.
- Planning validation: clean with 42 records and 32 active after closure synchronization.
- Two-axis review: no blocking Standards finding and no Spec finding after four targeted refinements.
