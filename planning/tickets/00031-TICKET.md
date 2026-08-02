---
id: T-00031
prd: PRD-00009
title: Add the tracked pre-commit build gate
status: ready-for-agent
blocked_by: T-00029
---

# Add the Tracked Pre-Commit Build Gate

## What to Build

Provide an opt-in tracked pre-commit hook that runs the exact default local build before a commit is created.
It works from any invocation directory, cannot prompt through Git's stdin, and blocks the commit with the
build failure while retaining Git's explicit human bypass.

## Blocked By

T-00029 — Deliver the Disposable Local Build and Dependency Modes.

## Acceptance Criteria

- [ ] The tracked pre-commit hook resolves and runs from the repository root.
- [ ] The hook disconnects stdin and delegates exactly to the default `./bin/build` contract.
- [ ] A build failure blocks the commit with the original non-zero exit status.
- [ ] Successful completion permits the commit without running another quality sequence.
- [ ] Contributor documentation explains opt-in activation through `core.hooksPath`.
- [ ] Documentation names Git's explicit `--no-verify` escape hatch.
- [ ] No duplicate complete pre-push gate is added.
- [ ] Focused process tests prove root resolution, stdin handling, delegation, and status propagation.

## Parent

PRD-00009 — Build, Dependency, and Coverage Verification.
