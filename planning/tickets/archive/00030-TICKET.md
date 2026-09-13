---
id: T-00030
prd: PRD-00009
title: Run latest-compatible verification in CI
status: done
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

- [x] CI resolves the latest dependency versions permitted by the package manifest instead of installing the
  checked-in resolution as its compatibility proof.
- [x] The CI-generated lockfile remains ephemeral and is not committed or published.
- [x] CI invokes the shared executable quality gate directly on the hosted runner without Docker.
- [x] Workflow configuration does not maintain a second ordered quality-command sequence.
- [x] Pull requests targeting main, develop, supported 1.x branches, and release branches continue to trigger
  verification.
- [x] Dependency resolution and gate failures propagate as failed CI jobs with clear step output.
- [x] CI documentation distinguishes latest-compatible hosted verification from reproducible local builds.

## Parent

PRD-00009 — Build, Dependency, and Coverage Verification.

## Outcome

Hosted CI now resolves the newest dependency versions permitted by `composer.json` through a visibly named,
non-interactive Composer update and then invokes `./bin/quality` directly on the runner with the existing
MySQL and PostgreSQL service DSNs. The workflow retains pull-request verification for `main`, `develop`,
supported `1.*` lines, and `release/*`, while the generated lockfile remains ephemeral and the ordered quality
sequence has one owner. Contributor documentation distinguishes reproducible locked local builds, explicit
local latest-compatible updates, and ephemeral hosted verification.

## Verification

- The final canonical `./bin/build` passed 3,108 tests and 5,876 assertions with zero skips and exact Clover
  coverage at 9,039/9,039 statements and 1,863/1,863 methods.
- Focused workflow-contract coverage passed 8 tests and 83 assertions, including rejected failure suppression,
  duplicate executable commands, Docker-wrapped execution, and literal or folded command blocks; harmless
  comment-only command mentions remain accepted.
- Composer validation, deterministic PHP syntax, planning integrity, PHPCS, PHPStan, both Deptrac checks,
  Rector dry-run, `git diff --check`, and disposable database cleanup passed.
- Fresh independent Spec and Standards re-reviews reported zero findings across exactly the three approved
  implementation paths.
