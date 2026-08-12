---
id: T-00032
prd: PRD-00010
title: Establish release inspection, plans, and boundary fakes
status: ready-for-agent
blocked_by:
---

# Establish Release Inspection, Plans, and Boundary Fakes

## What to Build

Implement the public inspection and planning journey behind `bin/release`. An operator can inspect a
candidate, approve an exact version, create an immutable content-addressed plan, and receive a versioned
machine result without performing a release effect. Establish the capability boundary and deterministic
fakes/effect ledgers required by later journeys.

## Acceptance Criteria

- [ ] Inspection recommends the minimum valid SemVer increment without making it authoritative.
- [ ] Plan creation requires approval of one exact version and binds every agreed immutable input.
- [ ] Canonically equivalent inputs produce the same `plan_id`; any material bound-input change produces a
      different identity.
- [ ] Every invocation emits the versioned machine result, stable exit classification, detailed findings,
      proposed effects, and exactly one next action.
- [ ] Boundary fakes cover success, refusal, failure, uncertainty, drift, and configured crash points without
      loading production credentials or performing external mutation.
- [ ] `bin/release` rejects commands and effects outside their declared capability boundary before recording
      an effect.

## Verification

Full submit gate, `./bin/planning-check`, and public-command inspection and planning journey tests.

## Parent

PRD-00010 — Deterministic Release Foundation.
