---
id: T-00012
prd: PRD-00005
title: Isolate synchronous dispatcher handler failures
status: done
blocked_by: T-00002
---

# Isolate Synchronous Dispatcher Handler Failures

## What to Build

Ensure one failing synchronous event handler cannot prevent later matching handlers from receiving the same event. Preserve both priority phases and report every handler failure together after fan-out completes.

## Blocked By

- T-00002 — Establish Event Sourcing context and decisions.

## Acceptance

- [x] Synchronous dispatch preserves two phases: event-specific handlers by priority, followed by `AllEvents` handlers by priority.
- [x] Each invoked handler catches any `Throwable`; later handlers still receive the same event message within and across phases.
- [x] After completed fan-out, one `EventDispatchFailed` contains ordered `EventHandlerFailure` values with callable descriptions and original throwables.
- [x] Object methods use FQCN plus method, named functions use their name, and closures use a diagnostic non-replayable descriptor.
- [x] Handler-resolution and dispatcher-infrastructure failures remain outside the aggregate and propagate immediately.
- [x] Both the simple and service-aware synchronous dispatchers satisfy the same public contract with complete coverage.

## Outcome

`SimpleEventDispatcher` now isolates every invoked handler `Throwable`, preserves event-specific then `AllEvents`
priority phases, and throws one ordered `EventDispatchFailed` after complete fan-out. `EventHandlerFailure` retains
the operational callable description and original throwable. Service-backed handlers satisfy the same contract,
while container resolution failures remain unaggregated infrastructure failures.

The service-aware slice required no production change because it inherits the completed behavior from the simple
dispatcher; contract and infrastructure-failure tests were added without manufacturing an unrelated container-call
count requirement. The final submit gate passed Rector, PHPStan, PHPCS, 2,943 PHPUnit tests with 4,713 assertions,
and planning validation. Thirty optional database tests remained skipped because external MySQL/PostgreSQL DSNs were
not configured; all production classes changed by this ticket have complete coverage.
