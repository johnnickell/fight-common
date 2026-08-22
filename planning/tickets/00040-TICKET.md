---
id: T-00040
prd: PRD-00010
title: Prove resumable release runs and phase handoffs
status: done
blocked_by: T-00032
---

# Prove Resumable Release Runs and Phase Handoffs

## What to Build

Advance an approved plan through uniquely identified execution attempts whose append-only transitions,
atomically visible state, phase handoffs, and evidence survive interruption. Resume a named run only after
revalidating its bound inputs and every claimed postcondition.

## Acceptance Criteria

- [x] Each execution attempt receives a unique `run_id` associated with exactly one immutable plan; retry
      creates a new run while resume continues the named run.
- [x] Transitions remain append-only and ordered, while the replaceable current-state projection becomes
      visible atomically and preserves the prior valid state after an interrupted write.
- [x] Concurrent mutation of one run fails closed or is serialized before either invocation advances it.
- [x] Progress and stop states emit the required evidence and exactly one permitted next operation or human
      action; generic failure cannot replace a classified stop.
- [x] Resume re-resolves bound inputs and postconditions, reports verified already-satisfied work as
      idempotent success, and stops on drift or uncertainty.
- [x] Phase handoffs and evidence manifests are canonical, content-addressed, and reject missing, stale, or
      contradictory bindings.

## Verification

Full submit gate, `./bin/planning-check`, state-store and handoff conformance suites, concurrent-writer tests,
and public-command crash and resume journeys.

## Parent

PRD-00010 — Deterministic Release Foundation.

## Outcome

Delivered uniquely identified preparation attempts with immutable plan binding, append-only transition history,
atomic current-state projections, single-writer predecessor checks, named resume, and crash-safe compensation.
Preparation now revalidates live Git and current release authority, emits canonical content-addressed evidence and
phase handoffs, preserves precise governed stops, and distinguishes unchanged idempotent success from work completed
during resume. Descriptor-relative storage, exact artifact proof, bounded helper transport, and no-replace absent-run
publication make concurrent, interrupted, missing, stale, or contradictory state fail closed without external
release effects.

The canonical `./bin/build` passed 3,513 tests and 11,964 assertions with exact 13,886/13,886 statement coverage.
Planning integrity, PHPCS, PHPStan, architecture, Rector, PHP/Python syntax, PHPUnit, and exact coverage all passed.
Fresh independent Spec and Standards reviews reported no hard findings. No commit, push, pull request, publication,
credential use, external provider mutation, or worktree cleanup was performed.
