---
id: T-00008
prd: PRD-00004
title: Implement the Doctrine DBAL Event Store
status: done
blocked_by: T-00007
---

# Implement the Doctrine DBAL Event Store

## What to Build

Persist the complete Event Store contract through Doctrine DBAL on supported SQLite and MySQL-compatible databases without weakening append atomicity, retry classification, schema evolution, or checkpoint-safe global ordering.

## Blocked By

- T-00007 — Implement the in-memory Event Store.

## Acceptance

- [x] SQLite and MySQL-compatible schemas preserve stream uniqueness, message-ID uniqueness, and the complete stored-event envelope.
- [x] Expected-version validation, exact-batch retry detection, global-position allocation, and append occur in one transaction.
- [x] MySQL global positions are allocated through transaction-serialized sequence state so visible positions cannot later be preceded by a lower commit.
- [x] SQLite satisfies the same observable prefix-stable visibility contract.
- [x] Payload and metadata JSON, UTC microsecond timestamps, stable aliases, and original schema versions round-trip portably.
- [x] DBAL behavior passes the reusable Event Store conformance suite for SQLite and the supported MySQL contract.
- [x] Database-specific failure paths and transaction behavior have complete coverage.

## Outcome

Added `DbalEventStore` and `DbalEventStoreSchema` for SQLite and MySQL-compatible databases. The adapter owns
mapped append and hydration, complete envelope persistence, stream/message/global uniqueness, transactional
expected-version and exact-retry classification, and prefix-stable global-position allocation. SQLite acquires
its serialized writer reservation through the singleton sequence row; MySQL locks that row with `FOR UPDATE`
through commit.

The reusable Event Store conformance suite now runs against both database adapters. Focused two-connection
tests prove bounded lock contention, complete rollback, and contiguous committed positions without asserting
private SQL order. Unique races are reclassified only after rollback: exact positional retries succeed,
proven stream/message conflicts become `OptimisticConcurrencyException`, and unrelated DBAL, JSON, and commit
failures propagate.

CI provisions MySQL 8.4.11 plus both PDO drivers and supplies the MySQL test DSN. Unsupported DBAL platforms
fail explicitly rather than silently weakening the ordering contract.

## Verification

- Rector dry-run: clean across 381 files.
- PHPStan: clean across 381 files.
- PHPCS: clean.
- PHPUnit: 2,901 tests and 4,607 assertions passed; 13 MySQL tests skipped only in the DSN-free full local run.
- MySQL 8.4.11 focused verification: 13 tests and 81 assertions passed against the final code.
- Coverage: `DbalEventStore` 150/150 statements; `DbalEventStoreSchema` 36/36 statements.
- Planning validation: clean with 42 records and 31 active after closure synchronization.
- Two-axis review: no Spec findings and no documented Standards violations or blocking smells.
