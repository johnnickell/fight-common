---
id: T-00004
prd: PRD-00003
title: Implement aggregate lifecycle
status: done
blocked_by: T-00002
---

# Implement Aggregate Lifecycle

## What to Build

Let a consumer define an event-sourced aggregate that records new events, replays ordered history, exposes its domain-specific identity and current version, releases pending work once, and owns its reconstruction rules without framework or public-constructor requirements.

## Blocked By

- T-00002 — Establish Event Sourcing context and decisions.

## Acceptance

- [x] A framework-free `EventSourcedAggregate` contract and reference `AggregateRoot` expose a consumer-owned `Identifier`, current version, pending-event release, and static reconstitution.
- [x] Recording routes plain events through explicit `apply()` behavior, increments version, and appends the event only after successful application.
- [x] Failed event application does not increment version or create a pending event.
- [x] Reconstitution accepts ordered plain events and applies them without re-recording.
- [x] Unknown events fail closed unless the aggregate defines an explicit no-op transition.
- [x] Empty aggregates begin at version zero; release returns events in order and clears pending state immediately.
- [x] Behavior has complete coverage without requiring a public zero-argument constructor.

## Outcome

Added the framework-free `EventSourcedAggregate` contract, the reference `AggregateRoot`, and a dedicated
`UnrecognizedEventException`. Consumer aggregates now own explicit event routing and private-constructor
reconstitution while the base class consistently tracks versions, recorded events, replay, and one-time
ordered release.

## Verification

- Rector dry-run: clean.
- PHPStan: clean.
- PHPCS: clean.
- PHPUnit: 2,833 tests and 4,384 assertions passed.
- Coverage: 7,571/7,571 statements and 1,687/1,687 methods.
- Planning validation: 42 records, 36 active.
- Two-axis review: no Standards or Spec findings after one targeted refinement.
