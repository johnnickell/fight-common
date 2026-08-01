---
id: T-00008
prd: PRD-00004
title: Implement the Doctrine DBAL Event Store
status: ready-for-agent
blocked_by: T-00007
---

# Implement the Doctrine DBAL Event Store

## What to Build

Persist the complete Event Store contract through Doctrine DBAL on supported SQLite and MySQL-compatible databases without weakening append atomicity, retry classification, schema evolution, or checkpoint-safe global ordering.

## Blocked By

- T-00007 — Implement the in-memory Event Store.

## Acceptance

- [ ] SQLite and MySQL-compatible schemas preserve stream uniqueness, message-ID uniqueness, and the complete stored-event envelope.
- [ ] Expected-version validation, exact-batch retry detection, global-position allocation, and append occur in one transaction.
- [ ] MySQL global positions are allocated through transaction-serialized sequence state so visible positions cannot later be preceded by a lower commit.
- [ ] SQLite satisfies the same observable prefix-stable visibility contract.
- [ ] Payload and metadata JSON, UTC microsecond timestamps, stable aliases, and original schema versions round-trip portably.
- [ ] DBAL behavior passes the reusable Event Store conformance suite for SQLite and the supported MySQL contract.
- [ ] Database-specific failure paths and transaction behavior have complete coverage.
