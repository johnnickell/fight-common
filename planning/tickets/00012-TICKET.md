---
id: T-00012
prd: PRD-00005
title: Isolate synchronous dispatcher handler failures
status: ready-for-agent
blocked_by: T-00002
---

# Isolate Synchronous Dispatcher Handler Failures

## What to Build

Ensure one failing synchronous event handler cannot prevent later matching handlers from receiving the same event. Preserve both priority phases and report every handler failure together after fan-out completes.

## Blocked By

- T-00002 — Establish Event Sourcing context and decisions.

## Acceptance

- [ ] Synchronous dispatch preserves two phases: event-specific handlers by priority, followed by `AllEvents` handlers by priority.
- [ ] Each invoked handler catches any `Throwable`; later handlers still receive the same event message within and across phases.
- [ ] After completed fan-out, one `EventDispatchFailed` contains ordered `EventHandlerFailure` values with callable descriptions and original throwables.
- [ ] Object methods use FQCN plus method, named functions use their name, and closures use a diagnostic non-replayable descriptor.
- [ ] Handler-resolution and dispatcher-infrastructure failures remain outside the aggregate and propagate immediately.
- [ ] Both the simple and service-aware synchronous dispatchers satisfy the same public contract with complete coverage.
