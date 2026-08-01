---
id: T-00023
prd: PRD-00009
title: Enforce the complete build and dependency verification pipeline
status: ready-for-agent
blocked_by: T-00021,T-00022
---

# Enforce the Complete Build and Dependency Verification Pipeline

## Acceptance

- `bin/build` builds the PHP image once and runs the complete local quality pipeline in one disposable container.
- `bin/build --latest` explicitly refreshes local Composer dependencies before verification; the default local build uses the current resolution.
- CI resolves the latest dependency versions permitted by `composer.json` before running direct, non-Docker quality commands.
- The ordered pipeline covers Composer validation, PHP syntax, planning integrity, PHPCS, PHPStan, Deptrac, Rector dry-run, PHPUnit, and coverage enforcement.
- The coverage gate rejects a missing or malformed Clover report and requires covered statements to equal all executable statements without percentage rounding.
- Coverage-gate and build-wrapper failure behavior has focused automated tests.
- Local and CI documentation names the same authoritative gates while preserving their different execution models.
