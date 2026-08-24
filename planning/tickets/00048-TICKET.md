---
id: T-00048
prd: PRD-00014
title: Restore Scheduler 1.x Construction Compatibility
status: done
blocked_by: T-00047
---

# Restore Scheduler 1.x Construction Compatibility

## What to Build

Restore the complete published `1.1.0` Scheduler construction and command-execution journey while adding a named
portable `ProcessRunner` construction path. Existing positional, named-argument, and process-factory consumers
continue to work through `1.x`; new consumers can choose the inward-facing process abstraction explicitly.

## Acceptance Criteria

- [x] The legacy constructor preserves the published parameter order, names, defaults, and accepted values for
      both positional and named-argument consumers.
- [x] Legacy callable jobs, command jobs, locking, output capture, failure reporting, logging, and notification
      behavior remain compatible with `1.1.0`.
- [x] The legacy process-factory and conditional Symfony Process path remain functional and are documented as
      deprecated without emitting runtime deprecation warnings.
- [x] `withProcessRunner` constructs a Scheduler through the portable `ProcessRunner` contract without inserting
      a new required argument into the legacy constructor.
- [x] Application code gains no unconditional Symfony dependency; the legacy execution path remains a narrow
      compatibility exception rather than a precedent for new framework coupling.
- [x] Installed-package probes compile and execute every published positional and named construction style
      against both the authoritative baseline and the candidate.
- [x] A faithful compatibility bridge is proven before the candidate is accepted. If the published behavior
      cannot be reproduced, the incompatible required-runner form is not shipped and the ticket stops for an
      explicit `2.0.0` replan.
- [x] Scheduler declarations and behaviors are linked to the public manifest and stable compatibility findings.

## Verification

Full submit gate, `./bin/planning-check`, focused Scheduler tests, baseline/candidate consumer probes, and
architecture validation proving no new outward Application dependency.

## Implementation Evidence

- Restored the exact published `1.1.0` constructor and legacy callable/command paths while adding the named
  `withProcessRunner` composition without an unconditional Symfony dependency.
- Installed baseline and candidate probes run as distinct copied packages and authenticate generic public-API and
  Scheduler-specific envelopes before composing deterministic receipts.
- The compatibility authority verifies construction, output, non-zero reporting, logging, notification, retry,
  lock release, runtime-deprecation absence, and portable-runner behavior. Authenticated incompatibility returns
  the exact exit-`4` `2.0.0` replan; malformed or unavailable evidence remains exit `5`.
- `./bin/release compatibility` succeeds with exit `0`. The canonical `./bin/build` passes 3,608 tests and 12,901
  assertions with exact 16,906/16,906 statement coverage; fresh Standards and Spec reviews are clean.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.
