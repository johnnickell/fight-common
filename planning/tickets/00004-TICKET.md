---
id: T-00004
prd: PRD-00003
title: Implement aggregate lifecycle
status: ready-for-agent
blocked_by: T-00002
---

# Implement Aggregate Lifecycle

## What to Build

Let a consumer define an event-sourced aggregate that records new events, replays ordered history, exposes its domain-specific identity and current version, releases pending work once, and owns its reconstruction rules without framework or public-constructor requirements.

## Blocked By

- T-00002 — Establish Event Sourcing context and decisions.

## Acceptance

- [ ] A framework-free `EventSourcedAggregate` contract and reference `AggregateRoot` expose a consumer-owned `Identifier`, current version, pending-event release, and static reconstitution.
- [ ] Recording routes plain events through explicit `apply()` behavior, increments version, and appends the event only after successful application.
- [ ] Failed event application does not increment version or create a pending event.
- [ ] Reconstitution accepts ordered plain events and applies them without re-recording.
- [ ] Unknown events fail closed unless the aggregate defines an explicit no-op transition.
- [ ] Empty aggregates begin at version zero; release returns events in order and clears pending state immediately.
- [ ] Behavior has complete coverage without requiring a public zero-argument constructor.
