---
id: T-00040
prd: PRD-00010
title: Prove resumable release runs and phase handoffs
status: ready-for-agent
blocked_by: T-00032
---

# Prove Resumable Release Runs and Phase Handoffs

## What to Build

Advance an approved plan through uniquely identified execution attempts whose append-only transitions,
atomically visible state, phase handoffs, and evidence survive interruption. Resume a named run only after
revalidating its bound inputs and every claimed postcondition.

## Acceptance Criteria

- [ ] Each execution attempt receives a unique `run_id` associated with exactly one immutable plan; retry
      creates a new run while resume continues the named run.
- [ ] Transitions remain append-only and ordered, while the replaceable current-state projection becomes
      visible atomically and preserves the prior valid state after an interrupted write.
- [ ] Concurrent mutation of one run fails closed or is serialized before either invocation advances it.
- [ ] Progress and stop states emit the required evidence and exactly one permitted next operation or human
      action; generic failure cannot replace a classified stop.
- [ ] Resume re-resolves bound inputs and postconditions, reports verified already-satisfied work as
      idempotent success, and stops on drift or uncertainty.
- [ ] Phase handoffs and evidence manifests are canonical, content-addressed, and reject missing, stale, or
      contradictory bindings.

## Verification

Full submit gate, `./bin/planning-check`, state-store and handoff conformance suites, concurrent-writer tests,
and public-command crash and resume journeys.

## Parent

PRD-00010 — Deterministic Release Foundation.
