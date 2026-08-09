---
id: T-00013
prd: PRD-00005
title: Publish committed events with in-memory operational state
status: done
blocked_by: T-00007,T-00012
---

# Publish Committed Events With In-Memory Operational State

## What to Build

Let a named publication worker dispatch only committed stored events, record one bounded operational failure after completed fan-out, and advance an independent attempted-delivery cursor without automatically redispatching subscriber failures.

## Blocked By

- T-00007 — Implement the in-memory Event Store.
- T-00012 — Isolate synchronous dispatcher handler failures.

## Acceptance

- [x] A stably named `EventPublicationRunner` reads only committed stored events and dispatches their hydrated messages through `SynchronousEventDispatcher`.
- [x] Its in-memory `PublicationCursorStore` advances monotonically after every completed fan-out, including successfully recorded handler failures, and exposes no reset operation.
- [x] One idempotent in-memory `PublicationFailureRecorder` records the aggregated failure for each publication name and global position before cursor advancement.
- [x] Failure snapshots include dispatch-start time, message and event identity, callable descriptions, exception class and code, and normalized 4 KiB diagnostic messages.
- [x] Durable snapshots exclude payloads, metadata, traces, and original throwables.
- [x] Unexpected dispatcher, recorder, and cursor-store failures propagate without cursor advancement.
- [x] Successful publication, handler failure, independent publication names, and crash-retry duplicate delivery have complete coverage.

## Outcome

Added the stably named `EventPublicationRunner`, its monotonic no-reset
`PublicationCursorStore`, and an idempotent `PublicationFailureRecorder` with
in-memory reference adapters. The runner reads only committed hydrated messages
from the Event Store, records completed synchronous fan-out failures before
advancing, and propagates uncertain dispatcher, recorder, and cursor failures
without false progress. Portable failure snapshots retain only bounded
operational identity and diagnostics; tests prove successful publication,
ordered aggregated handler failures, independent publication names, and
crash-retry duplicate delivery.

Rector, PHPStan, PHPCS, the full PHPUnit suite, focused exact coverage,
planning validation, and both Standards and Spec review axes passed. The full
suite skipped the thirty optional external MySQL/PostgreSQL tests; the resulting
six uncovered statements are the pre-existing DBAL projection-checkpoint race
branch, while every T-00013 production class has exact coverage.
