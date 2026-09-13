---
id: T-00028
prd: PRD-00009
title: Establish the shared executable quality gate
status: done
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

- [x] One executable gate owns Composer validation, PHP syntax, planning integrity, PHPCS, PHPStan, Deptrac,
  Rector dry-run, PHPUnit, and exact coverage enforcement.
- [x] Every step has a visible name and executes in the accepted order.
- [x] The gate stops at the first failure and propagates that command's non-zero status.
- [x] Successful completion proves every ordered step ran in the current invocation.
- [x] The exact Clover artifact consumed by coverage enforcement is removed before PHPUnit starts.
- [x] The gate is host-neutral and contains no Docker, GitHub Actions, or interactive-wrapper assumptions.
- [x] Focused process tests prove ordering, fail-fast behavior, status propagation, and stale-report removal.

## Parent

PRD-00009 — Build, Dependency, and Coverage Verification.

## Outcome

Added `bin/quality` as the single host-neutral ordered submit-gate definition for agents and CI-prepared
environments. It visibly announces Composer validation, deterministic first-party PHP syntax validation,
planning integrity, PHPCS, PHPStan, both mandatory Deptrac commands, Rector dry-run, PHPUnit, and exact coverage;
it fails at the first broken contract with that command's status. The gate removes the exact Clover artifact
immediately before PHPUnit, so coverage enforcement can consume only evidence produced by the current invocation.

## Verification

- Focused process coverage passes 8 tests and 19 assertions for complete ordered success, early/middle/late
  failures, both independent Deptrac commands, exact status propagation, and stale/current Clover behavior.
- The complete disposable MySQL 8.4.11 and PostgreSQL 17 suite passes 3,095 tests and 5,758 assertions with zero
  skips; Clover coverage is exact at 9,039/9,039 statements.
- Strict Composer validation, deterministic PHP syntax, PHPCS, PHPStan, both Deptrac commands, Rector dry-run,
  planning validation, shell syntax, and `git diff --check` pass.
- Independent Spec review reports zero findings. Independent Standards review reports no blocking findings and
  one non-blocking duplicated test-cleanup helper observation.
