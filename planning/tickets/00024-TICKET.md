---
id: T-00024
prd: PRD-00009
title: Make Scheduler coverage exact
status: ready-for-agent
blocked_by: T-00022
---

# Make Scheduler Coverage Exact

## What to Build

Use the Scheduler's repaired `ProcessRunner` boundary and deterministic collaborators to execute every
previously excluded scheduling branch. Preserve locking, runtime expiry, output, logging, notification, and
cleanup behavior while removing Scheduler's coverage exceptions.

## Blocked By

T-00022 — Introduce Mandatory Architecture Enforcement.

## Acceptance Criteria

- [ ] Maximum-runtime inspection failures are exercised through deterministic tests.
- [ ] Lock creation, contention, unexpected lock failures, and lock release are covered through observable
  Scheduler behavior.
- [ ] Command output and failure behavior execute through a deterministic `ProcessRunner` substitute.
- [ ] Logging and notification outcomes remain correct for every exercised failure path.
- [ ] Every production coverage-ignore directive owned by Scheduler is removed.
- [ ] Scheduler's public API and runtime behavior remain compatible.
- [ ] The existing submit gate remains green with exact complete statement coverage for the measured source.

## Parent

PRD-00009 — Build, Dependency, and Coverage Verification.
