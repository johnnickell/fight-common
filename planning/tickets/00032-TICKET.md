---
id: T-00032
prd: PRD-00010
title: Establish release plans, runs, handoffs, and boundary fakes
status: ready-for-agent
blocked_by:
---

# Establish Release Plans, Runs, Handoffs, and Boundary Fakes

## What to Build

Implement the immutable plan/run foundation behind `bin/release`, including canonical JSON digests,
phase handoffs, machine results, postcondition-driven resume, and deterministic fakes/effect ledgers
for Git, signing, authorization, GitHub, and Packagist.

## Acceptance Criteria

- [ ] Plans and handoffs contain the agreed immutable bindings and exactly one next action.
- [ ] Bound-input drift creates a new plan identity and resume revalidates postconditions.
- [ ] Boundary fakes cover success, failure, uncertainty, and crash points without external mutation.
- [ ] Unit and contract tests cover the public seams completely.
- [ ] `bin/release` rejects commands outside their declared capability boundary.

## Verification

Full submit gate, `./bin/planning-check`, and deterministic offline journey tests.

## Parent

PRD-00010 — Deterministic Release Foundation.
