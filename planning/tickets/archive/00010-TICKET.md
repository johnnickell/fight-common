---
id: T-00010
prd: PRD-00005
title: Run projections with in-memory checkpoints
status: done
blocked_by: T-00007
---

# Run Projections With In-Memory Checkpoints

## What to Build

Let a stably named projector consume already-upcasted stored events in global order, update read state at least once, checkpoint every successful event or skip, and retry the first failed position without processing later events.

## Blocked By

- T-00007 — Implement the in-memory Event Store.

## Acceptance

- [x] Each projector declares a stable name and the current event payload FQCNs it handles.
- [x] Projectors receive already-upcasted `StoredEvent` values and are documented as requiring idempotent read-state operations.
- [x] `ProjectionRunner` polls bounded global batches and preserves global order.
- [x] The checkpoint advances after every successful projection or undeclared-event skip.
- [x] A projector failure stops immediately, leaves the failed event uncheckpointed, propagates, and prevents later positions from running.
- [x] In-memory checkpoint saves are monotonic; explicit reset returns only the named projector to zero.
- [x] Replay, skip, failure, crash-duplicate, and adding-a-type rebuild scenarios have complete coverage.

## Outcome

Added framework-free projector and checkpoint contracts, a one-batch `ProjectionRunner`, and the in-memory
checkpoint reference adapter. Projectors route on already-upcasted current payload classes, declared events
and undeclared skips advance independently in global order, and failures stop without checkpointing the
failed position. Checkpoints are isolated and monotonic, while explicit named reset supports consumer-owned
read-model rebuilds without adding arbitrary rewind or worker policy.

Tests prove historical upcasting before projection, bounded polling, per-event checkpoint saves, fail-stop
retry, the crash-after-write duplicate window, reset isolation, and rebuilding history after adding a handled
type.

## Verification

- Rector dry-run: clean across 387 source files.
- PHPStan: clean across 387 files.
- PHPCS: clean.
- PHPUnit: 2,932 tests and 4,675 assertions passed; 26 environment-dependent tests skipped.
- Coverage: 8,002/8,002 statements and 1,756/1,756 methods.
- Planning validation: clean with 59 records and 45 active after closure synchronization.
- Two-axis review: zero blocking Standards findings and zero Spec findings; one nonblocking test-local
  duplicated-stub judgment remains.
