---
id: T-00027
prd: PRD-00009
title: Enforce zero-exclusion exact coverage
status: ready-for-agent
blocked_by: T-00023,T-00024,T-00025,T-00026
---

# Enforce Zero-Exclusion Exact Coverage

## What to Build

Turn complete production statement coverage into a fail-closed executable contract. The coverage gate rejects
every production exclusion directive, ignores stale evidence, parses only the current Clover report, and
accepts a run only when covered statements equal all executable statements exactly.

## Blocked By

- T-00023 — Eliminate Core Coverage Exclusions.
- T-00024 — Make Scheduler Coverage Exact.
- T-00025 — Cover Adapter Failure Boundaries.
- T-00026 — Cover Process and FTP Integration Boundaries.

## Acceptance Criteria

- [ ] Production source contains no `@codeCoverageIgnore`, `@codeCoverageIgnoreStart`, or
  `@codeCoverageIgnoreEnd` directive.
- [ ] The gate fails when any forbidden directive is introduced into production source.
- [ ] The gate fails when Clover is absent, malformed, or lacks the required project statement metrics.
- [ ] The gate fails whenever covered statements are fewer than executable statements, regardless of rounded
  percentage output.
- [ ] The gate passes only when covered statements equal executable statements exactly.
- [ ] Focused fixtures prove directive variants, missing and malformed reports, incomplete coverage, and exact
  equality.
- [ ] The complete existing test suite produces acceptable exact coverage evidence.

## Parent

PRD-00009 — Build, Dependency, and Coverage Verification.
