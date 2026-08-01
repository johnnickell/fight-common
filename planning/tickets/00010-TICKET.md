---
id: T-00010
prd: PRD-00005
title: Run projections with in-memory checkpoints
status: ready-for-agent
blocked_by: T-00007
---

# Run Projections With In-Memory Checkpoints

## What to Build

Let a stably named projector consume already-upcasted stored events in global order, update read state at least once, checkpoint every successful event or skip, and retry the first failed position without processing later events.

## Blocked By

- T-00007 — Implement the in-memory Event Store.

## Acceptance

- [ ] Each projector declares a stable name and the current event payload FQCNs it handles.
- [ ] Projectors receive already-upcasted `StoredEvent` values and are documented as requiring idempotent read-state operations.
- [ ] `ProjectionRunner` polls bounded global batches and preserves global order.
- [ ] The checkpoint advances after every successful projection or undeclared-event skip.
- [ ] A projector failure stops immediately, leaves the failed event uncheckpointed, propagates, and prevents later positions from running.
- [ ] In-memory checkpoint saves are monotonic; explicit reset returns only the named projector to zero.
- [ ] Replay, skip, failure, crash-duplicate, and adding-a-type rebuild scenarios have complete coverage.
