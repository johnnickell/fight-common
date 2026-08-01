---
id: T-00014
prd: PRD-00005
title: Persist and log publication operational state
status: ready-for-agent
blocked_by: T-00008,T-00013
---

# Persist and Log Publication Operational State

## What to Build

Persist publication progress and aggregated failure evidence through DBAL, and provide composable PSR-3 observability that logs the same portable snapshot without replacing the configured recorder.

## Blocked By

- T-00008 — Implement the Doctrine DBAL Event Store.
- T-00013 — Publish committed events with in-memory operational state.

## Acceptance

- [ ] DBAL publication cursor storage independently and monotonically tracks each stable publication name without a reset operation.
- [ ] DBAL failure recording idempotently stores one portable aggregated record per publication name and global position.
- [ ] SQLite and MySQL-compatible adapters satisfy the in-memory cursor and failure-recorder contracts.
- [ ] The PSR-3 logging recorder requires another recorder, logs the portable snapshot first, and then delegates the same record.
- [ ] Logging or delegation failure propagates and blocks cursor advancement.
- [ ] Retry duplicates remain correlatable by publication name and global position.
- [ ] Query, automatic replay, and targeted-replay APIs remain outside 1.2.
- [ ] Persistence and decorator behavior have complete coverage.
