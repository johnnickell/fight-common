---
id: T-00023
prd: PRD-00009
title: Eliminate core coverage exclusions
status: ready-for-agent
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

- [ ] Coverage-tool workarounds in core iterators and utilities are reverified against the current PHP,
  PHPUnit, and Xdebug versions.
- [ ] Obsolete coverage-tool workarounds are removed rather than retained as historical exceptions.
- [ ] Previously excluded validation and utility failure paths execute through deterministic tests.
- [ ] Every production coverage-ignore directive in this ticket's core scope is removed.
- [ ] Public APIs and runtime behavior are not weakened solely to satisfy coverage measurement.
- [ ] The existing submit gate remains green with exact complete statement coverage for the measured source.

## Parent

PRD-00009 — Build, Dependency, and Coverage Verification.
