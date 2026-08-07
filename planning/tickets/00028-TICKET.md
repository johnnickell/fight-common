---
id: T-00028
prd: PRD-00009
title: Establish the shared executable quality gate
status: ready-for-agent
blocked_by: T-00021,T-00022,T-00027
---

# Establish the Shared Executable Quality Gate

## What to Build

Provide one host-neutral executable definition of Fight Common's complete submit gate. It announces each
step, runs the accepted commands in order, removes stale coverage evidence before testing, and stops with the
first failing command's status so both local and CI entry points can delegate to one contract.

## Blocked By

- T-00021 — Migrate and Enable Documentation Rules.
- T-00022 — Introduce Mandatory Architecture Enforcement.
- T-00027 — Enforce Zero-Exclusion Exact Coverage.

## Acceptance Criteria

- [ ] One executable gate owns Composer validation, PHP syntax, planning integrity, PHPCS, PHPStan, Deptrac,
  Rector dry-run, PHPUnit, and exact coverage enforcement.
- [ ] Every step has a visible name and executes in the accepted order.
- [ ] The gate stops at the first failure and propagates that command's non-zero status.
- [ ] Successful completion proves every ordered step ran in the current invocation.
- [ ] The exact Clover artifact consumed by coverage enforcement is removed before PHPUnit starts.
- [ ] The gate is host-neutral and contains no Docker, GitHub Actions, or interactive-wrapper assumptions.
- [ ] Focused process tests prove ordering, fail-fast behavior, status propagation, and stale-report removal.

## Parent

PRD-00009 — Build, Dependency, and Coverage Verification.
