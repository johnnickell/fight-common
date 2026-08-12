---
id: T-00016
prd: PRD-00006
title: Document Event Sourcing integration and operations
status: done
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

- [x] Public documentation covers aggregate, mapper, repository, Event Store, projection, publication, failure recording, and separate worker wiring.
- [x] Manual framework-free construction is documented as the portable baseline.
- [x] At-least-once projector idempotency, publication crash duplicates, subscriber failure behavior, and cursor/checkpoint differences are explicit.
- [x] Migration guidance covers stable aliases, class refactors, upcasters, metadata snapshots, aggregate reload after failed save, and read-model rebuilds.
- [x] Failure-recording limits and consumer responsibility for safe exception messages are explicit.
- [x] The optional Symfony mapping-provider integration is documented only if the stretch ticket ships.
- [x] Event Sourcing appears in MkDocs navigation.
- [x] Documentation examples use only supported public contracts.

## Outcome

Added one navigable Event Sourcing integration and operations guide covering additive CQRS adoption, explicit
aggregate lifecycle, durable names and schemas, exact-retry Event Store behavior, repository save/reload,
prefix-stable projection polling and rebuilds, publication failure isolation and duplicates, migration, recovery,
and the delivered optional Symfony mapping-provider integration. Manual framework-free composition remains the
portable baseline.

Every PHP block is rendered from a named region in one test-owned executable fixture. That same fixture runs two
aggregate streams through SQLite DBAL persistence, projection/checkpoint progress, synchronous publication,
durable failure recording, and cursor advancement, while separate public-contract tests retain the complete
adapter and failure matrix.

## Verification

- Documentation contract: 8 tests and 158 assertions passed.
- Strictly scoped MkDocs Material 9.7.7 render: the Event Sourcing page and every named PHP snippet rendered;
  the repository retains one unrelated pre-existing `docs/contributing.md` out-of-tree-link warning.
- Rector dry-run, PHPStan, PHPCS, Deptrac, and unassigned-token checks: clean across 416 production files.
- Complete PHPUnit with disposable MySQL 8.4.11 and PostgreSQL 17: 3,087 tests and 5,739 assertions passed with
  zero skips.
- Clover: exact 9,039/9,039 statements and 1,863/1,863 methods.
- Final independent two-axis review: zero Standards findings and zero Spec findings after the scoped contributing
  link change was removed.
