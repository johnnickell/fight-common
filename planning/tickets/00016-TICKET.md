---
id: T-00016
prd: PRD-00006
title: Document Event Sourcing integration and operations
status: ready-for-agent
blocked_by: T-00009,T-00011,T-00014
---

# Document Event Sourcing Integration and Operations

## What to Build

Publish one complete guide for manually wiring and safely operating aggregates, mapping, storage, repositories, projections, publication, failure recording, migration, and recovery. Include Symfony provider autoconfiguration only if the stretch ticket ships.

## Blocked By

- T-00009 — Implement EventSourcedRepository.
- T-00011 — Persist projection checkpoints with DBAL.
- T-00014 — Persist and log publication operational state.

## Acceptance

- [ ] Public documentation covers aggregate, mapper, repository, Event Store, projection, publication, failure recording, and separate worker wiring.
- [ ] Manual framework-free construction is documented as the portable baseline.
- [ ] At-least-once projector idempotency, publication crash duplicates, subscriber failure behavior, and cursor/checkpoint differences are explicit.
- [ ] Migration guidance covers stable aliases, class refactors, upcasters, metadata snapshots, aggregate reload after failed save, and read-model rebuilds.
- [ ] Failure-recording limits and consumer responsibility for safe exception messages are explicit.
- [ ] The optional Symfony mapping-provider integration is documented only if the stretch ticket ships.
- [ ] Event Sourcing appears in MkDocs navigation.
- [ ] Documentation examples use only supported public contracts.
