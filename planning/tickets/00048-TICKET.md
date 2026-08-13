---
id: T-00048
prd: PRD-00014
title: Restore Scheduler 1.x Construction Compatibility
status: ready-for-agent
blocked_by: T-00047
---

# Restore Scheduler 1.x Construction Compatibility

## What to Build

Restore the complete published `1.1.0` Scheduler construction and command-execution journey while adding a named
portable `ProcessRunner` construction path. Existing positional, named-argument, and process-factory consumers
continue to work through `1.x`; new consumers can choose the inward-facing process abstraction explicitly.

## Acceptance Criteria

- [ ] The legacy constructor preserves the published parameter order, names, defaults, and accepted values for
      both positional and named-argument consumers.
- [ ] Legacy callable jobs, command jobs, locking, output capture, failure reporting, logging, and notification
      behavior remain compatible with `1.1.0`.
- [ ] The legacy process-factory and conditional Symfony Process path remain functional and are documented as
      deprecated without emitting runtime deprecation warnings.
- [ ] `withProcessRunner` constructs a Scheduler through the portable `ProcessRunner` contract without inserting
      a new required argument into the legacy constructor.
- [ ] Application code gains no unconditional Symfony dependency; the legacy execution path remains a narrow
      compatibility exception rather than a precedent for new framework coupling.
- [ ] Installed-package probes compile and execute every published positional and named construction style
      against both the authoritative baseline and the candidate.
- [ ] A faithful compatibility bridge is proven before the candidate is accepted. If the published behavior
      cannot be reproduced, the incompatible required-runner form is not shipped and the ticket stops for an
      explicit `2.0.0` replan.
- [ ] Scheduler declarations and behaviors are linked to the public manifest and stable compatibility findings.

## Verification

Full submit gate, `./bin/planning-check`, focused Scheduler tests, baseline/candidate consumer probes, and
architecture validation proving no new outward Application dependency.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.
