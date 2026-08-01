---
id: T-00011
prd: PRD-00005
title: Persist projection checkpoints with DBAL
status: ready-for-agent
blocked_by: T-00008,T-00010
---

# Persist Projection Checkpoints With DBAL

## What to Build

Persist each projector's monotonic progress and explicit reset-to-zero behavior through DBAL so production projections retain the same recovery guarantees as the in-memory contract.

## Blocked By

- T-00008 — Implement the Doctrine DBAL Event Store.
- T-00010 — Run projections with in-memory checkpoints.

## Acceptance

- [ ] The DBAL checkpoint store independently loads and monotonically saves each stable projector name.
- [ ] Explicit reset sets only the named projector to position zero.
- [ ] Arbitrary backward positions are rejected.
- [ ] SQLite and MySQL-compatible behavior matches the in-memory checkpoint contract under initial, duplicate, forward, backward, reset, and concurrent progress.
- [ ] Operational documentation requires stopping the projector worker and clearing or recreating its read model before reset.
- [ ] Adapter behavior has complete coverage.
