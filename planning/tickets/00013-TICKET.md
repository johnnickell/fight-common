---
id: T-00013
prd: PRD-00005
title: Publish committed events with in-memory operational state
status: ready-for-agent
blocked_by: T-00007,T-00012
---

# Publish Committed Events With In-Memory Operational State

## What to Build

Let a named publication worker dispatch only committed stored events, record one bounded operational failure after completed fan-out, and advance an independent attempted-delivery cursor without automatically redispatching subscriber failures.

## Blocked By

- T-00007 — Implement the in-memory Event Store.
- T-00012 — Isolate synchronous dispatcher handler failures.

## Acceptance

- [ ] A stably named `EventPublicationRunner` reads only committed stored events and dispatches their hydrated messages through `SynchronousEventDispatcher`.
- [ ] Its in-memory `PublicationCursorStore` advances monotonically after every completed fan-out, including successfully recorded handler failures, and exposes no reset operation.
- [ ] One idempotent in-memory `PublicationFailureRecorder` records the aggregated failure for each publication name and global position before cursor advancement.
- [ ] Failure snapshots include dispatch-start time, message and event identity, callable descriptions, exception class and code, and normalized 4 KiB diagnostic messages.
- [ ] Durable snapshots exclude payloads, metadata, traces, and original throwables.
- [ ] Unexpected dispatcher, recorder, and cursor-store failures propagate without cursor advancement.
- [ ] Successful publication, handler failure, independent publication names, and crash-retry duplicate delivery have complete coverage.
