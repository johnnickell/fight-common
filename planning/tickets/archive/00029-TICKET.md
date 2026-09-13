---
id: T-00029
prd: PRD-00009
title: Deliver the disposable local build and dependency modes
status: done
blocked_by: T-00028
---

# Deliver the Disposable Local Build and Dependency Modes

## What to Build

Provide the canonical non-interactive local build for humans, agents, and hooks. It builds the PHP image once,
runs the shared gate in one disposable container, preserves user ownership of mounted artifacts, and makes
locked versus latest-compatible dependency resolution an explicit choice.

## Blocked By

T-00028 — Establish the Shared Executable Quality Gate.

## Acceptance Criteria

- [x] The local build creates the PHP image once and invokes the shared gate in one disposable container.
- [x] The build allocates no TTY, issues no prompt, and uses non-interactive dependency commands.
- [x] The invoking user and group own lockfiles, installed dependencies, caches, and reports written through
  the mounted worktree.
- [x] Default mode installs the checked-in lockfile resolution and does not modify it.
- [x] `--latest` performs a complete Composer update, persists the resulting lockfile, and verifies that exact
  resolution.
- [x] A failed `--latest` verification leaves the updated lockfile visible for diagnosis and review.
- [x] Existing focused wrappers remain interactive conveniences and do not duplicate the complete sequence.
- [x] Focused process tests prove argument validation, container delegation, dependency modes, and exit
  propagation.

## Parent

PRD-00009 — Build, Dependency, and Coverage Verification.

## Outcome

Added `bin/build` as the canonical non-interactive local submit entry point. It builds the Composer-capable PHP
image once, reuses the disposable MySQL/PostgreSQL lifecycle, maps the invoking UID:GID, and delegates the sole
complete sequence to `bin/quality` in one disposable PHP container. The default path installs the tracked lock
without modifying it; `--latest` persists a complete update and retains that resolution after a failed gate.
Linked worktrees receive only their common Git metadata as a read-only mount.

## Verification

- The final real `./bin/build` passed from the linked worktree with 3,102 tests, 5,802 assertions, zero skips,
  and exact Clover coverage at 9,039/9,039 statements and 1,863/1,863 methods.
- Planning integrity, PHPCS, PHPStan, both Deptrac checks, Rector dry-run, shell syntax, and `git diff --check`
  passed; disposable database resources were cleaned after completion.
- Focused build and lifecycle process coverage passed 13 tests and 66 assertions, including argument rejection,
  one-container delegation, ownership, locked/latest modes, updated-lock persistence, cleanup, and exit status.
- Independent Standards and Spec reviews reported zero remaining findings; Spec cleared all eight acceptance
  criteria and all four expanded runtime/scope contracts.
