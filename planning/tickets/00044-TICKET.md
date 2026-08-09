---
id: T-00044
prd: PRD-00009
title: Eliminate environment-skipped database tests
status: ready-for-agent
blocked_by: T-00008,T-00011
---

# Eliminate Environment-Skipped Database Tests

## What to Build

Provide a repository-owned disposable MySQL and PostgreSQL test lifecycle so a
complete local suite executes every supported DBAL contract and fails rather
than silently skipping when its required database environment is unavailable.

## Blocked By

- T-00008 — Implement the Doctrine DBAL Event Store.
- T-00011 — Persist projection checkpoints with DBAL.

## Acceptance

- [ ] One non-interactive local entry point creates uniquely named disposable
  MySQL 8.4.11 and PostgreSQL 17 services, waits for both to become healthy,
  injects their `FIGHT_COMMON_*_DSN` values into the PHP test container, and
  runs the complete PHPUnit suite.
- [ ] Database containers and their isolated network are removed after success,
  failure, or interruption; no persistent or always-running service is added.
- [ ] The four MySQL/PostgreSQL Event Store and projection-checkpoint test
  classes no longer call `markTestSkipped()` when a DSN is absent; missing,
  malformed, or unreachable required database configuration fails clearly.
- [ ] The complete PHPUnit contract fails on any skipped test and cannot report
  a green result unless all supported database tests executed.
- [ ] A deliberately selected fast workflow may exclude the server-database
  group before discovery, reports zero skipped tests, and is explicitly not a
  submit or release gate.
- [ ] CI continues to use disposable MySQL and PostgreSQL services and proves
  the same skip-free complete-suite contract without duplicating test policy.
- [ ] Full Clover evidence reaches exact statement equality, including the DBAL
  projection-checkpoint concurrent reset recovery branch.
- [ ] Focused process tests prove image/service versions, unique resource names,
  health failure, DSN injection, exit propagation, and cleanup on every exit.
- [ ] Contributor documentation names the fast and complete commands and states
  that unavailable complete-suite infrastructure is a failure, never a skip.

## Out of Scope

- Persistent Docker Compose databases or manually managed local services.
- Changing Event Store, checkpoint, cursor, or failure-recorder behavior.
- The complete shared quality-gate and dependency-mode implementation owned by
  T-00028 through T-00031; those tickets compose this database lifecycle rather
  than redefining it.
- Weakening CI coverage or accepting a skip allowlist.
