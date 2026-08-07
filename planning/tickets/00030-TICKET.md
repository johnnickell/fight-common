---
id: T-00030
prd: PRD-00009
title: Run latest-compatible verification in CI
status: ready-for-agent
blocked_by: T-00028
---

# Run Latest-Compatible Verification in CI

## What to Build

Make hosted CI prove the newest dependency resolution permitted by the package manifest and then execute the
same authoritative quality gate directly on the runner. Preserve the repository's supported pull-request
branch coverage while removing duplicated gate commands from workflow configuration.

## Blocked By

T-00028 — Establish the Shared Executable Quality Gate.

## Acceptance Criteria

- [ ] CI resolves the latest dependency versions permitted by the package manifest instead of installing the
  checked-in resolution as its compatibility proof.
- [ ] The CI-generated lockfile remains ephemeral and is not committed or published.
- [ ] CI invokes the shared executable quality gate directly on the hosted runner without Docker.
- [ ] Workflow configuration does not maintain a second ordered quality-command sequence.
- [ ] Pull requests targeting main, develop, supported 1.x branches, and release branches continue to trigger
  verification.
- [ ] Dependency resolution and gate failures propagate as failed CI jobs with clear step output.
- [ ] CI documentation distinguishes latest-compatible hosted verification from reproducible local builds.

## Parent

PRD-00009 — Build, Dependency, and Coverage Verification.
