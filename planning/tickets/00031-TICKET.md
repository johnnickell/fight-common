---
id: T-00031
prd: PRD-00009
title: Add the tracked pre-commit build gate
status: done
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

- [x] The tracked pre-commit hook resolves and runs from the repository root.
- [x] The hook disconnects stdin and delegates exactly to the default `./bin/build` contract.
- [x] A build failure blocks the commit with the original non-zero exit status.
- [x] Successful completion permits the commit without running another quality sequence.
- [x] Contributor documentation explains opt-in activation through `core.hooksPath`.
- [x] Documentation names Git's explicit `--no-verify` escape hatch.
- [x] No duplicate complete pre-push gate is added.
- [x] Focused process tests prove root resolution, stdin handling, delegation, and status propagation.

## Parent

PRD-00009 — Build, Dependency, and Coverage Verification.

## Outcome

Added the tracked executable `.githooks/pre-commit` as an opt-in enforcement point. It resolves its own
repository root, changes to that root, disconnects stdin, and replaces itself with the argument-free default
`./bin/build`, preserving success or the original failure status without defining another quality sequence.
Contributor documentation now gives the repository-local `core.hooksPath` activation command and Git's explicit
`--no-verify` bypass; no pre-push hook was added.

## Verification

- The focused hook contract passed 2 tests and 8 assertions, proving nested-directory invocation, repository-root
  execution, zero arguments, stdin EOF, one build invocation, status `0`, status `37`, activation/bypass
  documentation, and absence of `.githooks/pre-push`.
- The canonical `./bin/build` passed 3,110 tests and 5,884 assertions with zero skips and exact Clover coverage
  at 9,039/9,039 statements.
- Planning integrity, PHPCS, PHPStan, both Deptrac checks, Rector dry-run, shell syntax, and `git diff --check`
  passed. Fresh independent Spec review reported zero findings; Standards reported no hard findings and one
  nonblocking existing test-cleanup duplication pattern intentionally left outside this ticket.
