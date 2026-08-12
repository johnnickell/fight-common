---
id: T-00011
prd: PRD-00005
title: Persist projection checkpoints with DBAL
status: done
blocked_by: T-00008,T-00010
---

# Persist Projection Checkpoints With DBAL

## What to Build

Persist each projector's monotonic progress and explicit reset-to-zero behavior through DBAL so production projections retain the same recovery guarantees as the in-memory contract.

## Blocked By

- T-00008 — Implement the Doctrine DBAL Event Store.
- T-00010 — Run projections with in-memory checkpoints.

## Acceptance

- [x] The DBAL checkpoint store independently loads and monotonically saves each stable projector name.
- [x] Explicit reset sets only the named projector to position zero.
- [x] Arbitrary backward positions are rejected.
- [x] SQLite, MySQL-compatible, and PostgreSQL behavior matches the in-memory checkpoint contract under initial, duplicate, forward, backward, reset, and concurrent progress.
- [x] Operational documentation requires stopping the projector worker and clearing or recreating its read model before reset.
- [x] Adapter behavior has complete coverage.

## Outcome

Added an independently installable `DbalProjectionCheckpointStoreSchema` and a durable
`DbalProjectionCheckpointStore` implementing the existing Application-layer checkpoint port. The adapter uses
database-enforced conditional advances plus concurrent-insert recovery so independent workers cannot regress a
stable projector's committed progress. Initial zero, duplicate and forward saves, arbitrary backward rejection,
stable-name isolation, and idempotent named reset match the in-memory reference contract.

One reusable lifecycle suite now covers the in-memory adapter and all DBAL backends. A shared two-process,
two-connection conformance test proves monotonic concurrent progress on SQLite, MySQL 8.4.11, and PostgreSQL 17.
Reset remains an administrative operation: consumers stop the worker and clear or recreate that projector's read
model before returning only its checkpoint to zero. The local PHP 8.5 image now includes `pdo_pgsql`, matching the
existing CI runtime without adding a persistent database service.

## Verification

- Rector dry-run: clean across 389 source files.
- PHPStan: clean across 389 source files.
- PHPCS: clean.
- Full PHPUnit after the final refinement: 2,938 tests and 4,701 assertions passed; 30 environment-gated server
  tests skipped in the DSN-free run.
- Focused in-memory, SQLite, MySQL, and PostgreSQL checkpoint matrix: 7 tests and 84 assertions passed against
  disposable MySQL 8.4.11 and PostgreSQL 17 services.
- Coverage: `DbalProjectionCheckpointStore` 51/51 statements and
  `DbalProjectionCheckpointStoreSchema` 9/9 statements.
- Disposable ticket database containers were removed after verification.
- Two-axis review: zero Spec findings and zero hard or blocking Standards findings; one nonblocking test-local
  DSN-wrapper duplication judgement remains.
