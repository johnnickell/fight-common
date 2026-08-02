---
id: T-00029
prd: PRD-00009
title: Deliver the disposable local build and dependency modes
status: ready-for-agent
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

- [ ] The local build creates the PHP image once and invokes the shared gate in one disposable container.
- [ ] The build allocates no TTY, issues no prompt, and uses non-interactive dependency commands.
- [ ] The invoking user and group own lockfiles, installed dependencies, caches, and reports written through
  the mounted worktree.
- [ ] Default mode installs the checked-in lockfile resolution and does not modify it.
- [ ] `--latest` performs a complete Composer update, persists the resulting lockfile, and verifies that exact
  resolution.
- [ ] A failed `--latest` verification leaves the updated lockfile visible for diagnosis and review.
- [ ] Existing focused wrappers remain interactive conveniences and do not duplicate the complete sequence.
- [ ] Focused process tests prove argument validation, container delegation, dependency modes, and exit
  propagation.

## Parent

PRD-00009 — Build, Dependency, and Coverage Verification.
