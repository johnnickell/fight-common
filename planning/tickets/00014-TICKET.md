---
id: T-00014
prd: PRD-00005
title: Persist and log publication operational state
status: done
blocked_by: T-00008,T-00013,T-00044
---

# Persist and Log Publication Operational State

## What to Build

Persist publication progress and aggregated failure evidence through DBAL, and provide composable PSR-3 observability that logs the same portable snapshot without replacing the configured recorder.

## Blocked By

- T-00008 — Implement the Doctrine DBAL Event Store.
- T-00013 — Publish committed events with in-memory operational state.
- T-00044 — Eliminate environment-skipped database tests.

## Acceptance

- [x] DBAL publication cursor storage independently and monotonically tracks each stable publication name without a reset operation.
- [x] DBAL failure recording idempotently stores one portable aggregated record per publication name and global position.
- [x] SQLite, MySQL-compatible, and PostgreSQL adapters satisfy the in-memory cursor and failure-recorder contracts.
- [x] The PSR-3 logging recorder requires another recorder, logs the portable snapshot first, and then delegates the same record.
- [x] Logging or delegation failure propagates and blocks cursor advancement.
- [x] Retry duplicates remain correlatable by publication name and global position.
- [x] Query, automatic replay, and targeted-replay APIs remain outside 1.2.
- [x] Persistence and decorator behavior have complete coverage.

## Outcome

Added independently installable DBAL publication-cursor and failure-recorder schemas, database-enforced monotonic
cursor advancement, transactional first-evidence failure recording, and shared in-memory/SQLite/MySQL/PostgreSQL
conformance. Added a required-delegate PSR-3 recorder that logs the portable snapshot before delegation and preserves
failure propagation through `EventPublicationRunner`. The complete skip-free matrix passes with 3,014 tests and 5,349
assertions, and production coverage remains exact at 8,822/8,822 statements.
