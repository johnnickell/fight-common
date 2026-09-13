---
id: T-00024
prd: PRD-00009
title: Make Scheduler coverage exact
status: done
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

- [x] Maximum-runtime inspection failures are exercised through deterministic tests.
- [x] Lock creation, contention, unexpected lock failures, and lock release are covered through observable
  Scheduler behavior.
- [x] Command output and failure behavior execute through a deterministic `ProcessRunner` substitute.
- [x] Logging and notification outcomes remain correct for every exercised failure path.
- [x] Every production coverage-ignore directive owned by Scheduler is removed.
- [x] Scheduler's public API and runtime behavior remain compatible.
- [x] The existing submit gate remains green with exact complete statement coverage for the measured source.

## Parent

PRD-00009 — Build, Dependency, and Coverage Verification.

## Outcome

Scheduler runtime inspection now covers empty, dead, active, expired, and failed lock states through exact-path
test controls that delegate to native PHP outside each test. Lock creation/open failures, real contention,
recursive acquisition, release, command output, and command failure execute through public Scheduler behavior
with deterministic `ProcessRunner`, logger, and mail observations. All ten Scheduler coverage directives are
removed without changing production logic or the public API, and a source-level regression guard prevents their
return.

## Verification

- Rector, PHPStan, PHPCS, both mandatory Deptrac checks, planning validation, and `git diff --check` pass.
- The complete disposable-database suite passes 3,071 tests with 5,537 assertions and zero skips.
- Clover coverage is exact at 9,033/9,033 statements and 1,862/1,862 methods overall.
- `Scheduler` is exact at 171/171 statements and 18/18 methods; production source has zero coverage directives.
- Independent Standards and Spec reviews pass after one test-helper namespace refinement; the Coordinate Build
  checklist grades 10/10.
