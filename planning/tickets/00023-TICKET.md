---
id: T-00023
prd: PRD-00009
title: Eliminate core coverage exclusions
status: done
blocked_by:
---

# Eliminate Core Coverage Exclusions

## What to Build

Remove the deterministic Domain and Application coverage exclusions that do not require infrastructure
redesign. Reverify alleged coverage-tool defects against current tooling and execute the affected validation,
iterator, and utility behavior through stable public seams.

## Blocked By

None — can start immediately.

## Acceptance Criteria

- [x] Coverage-tool workarounds in core iterators and utilities are reverified against the current PHP,
  PHPUnit, and Xdebug versions.
- [x] Obsolete coverage-tool workarounds are removed rather than retained as historical exceptions.
- [x] Previously excluded validation and utility failure paths execute through deterministic tests.
- [x] Every production coverage-ignore directive in this ticket's core scope is removed.
- [x] Public APIs and runtime behavior are not weakened solely to satisfy coverage measurement.
- [x] The existing submit gate remains green with exact complete statement coverage for the measured source.

## Parent

PRD-00009 — Build, Dependency, and Coverage Verification.

## Outcome

Fight Common now measures the core iterator, timezone-validation, and validation-service paths without inline
coverage exclusions. Public constructor and service tests preserve non-generator and unsupported-rule
failures, the unreachable post-parser fallback is removed, and timezone validation no longer depends on a
process-wide cache whose coverage attribution varied with test order. Public APIs and validation results are
unchanged.

## Verification

- PHP 8.5.7, PHPUnit 13.2.6, and Xdebug 3.5.3 reverified the former tool-defect claims.
- Rector, PHPStan, PHPCS, both mandatory Deptrac checks, planning validation, and `git diff --check` pass.
- The complete disposable-database suite passes 3,026 tests and 5,400 assertions without skips.
- Clover statement coverage is exact at 8,831/8,831 overall; all four touched production files are exact.
- Independent Standards and Spec reviews report zero findings; the ticket checklist grades 9/9.
