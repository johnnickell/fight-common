---
id: T-00044
prd: PRD-00009
title: Eliminate environment-skipped database tests
status: done
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

- [x] One non-interactive local entry point creates uniquely named disposable
  MySQL 8.4.11 and PostgreSQL 17 services, waits for both to become healthy,
  injects their `FIGHT_COMMON_*_DSN` values into the PHP test container, and
  runs the complete PHPUnit suite.
- [x] Database containers and their isolated network are removed after success,
  failure, or interruption; no persistent or always-running service is added.
- [x] The four MySQL/PostgreSQL Event Store and projection-checkpoint test
  classes no longer call `markTestSkipped()` when a DSN is absent; missing,
  malformed, or unreachable required database configuration fails clearly.
- [x] The complete PHPUnit contract fails on any skipped test and cannot report
  a green result unless all supported database tests executed.
- [x] A deliberately selected fast workflow may exclude the server-database
  group before discovery, reports zero skipped tests, and is explicitly not a
  submit or release gate.
- [x] CI continues to use disposable MySQL and PostgreSQL services and proves
  the same skip-free complete-suite contract without duplicating test policy.
- [x] Full Clover evidence reaches exact statement equality, including the DBAL
  projection-checkpoint concurrent reset recovery branch.
- [x] Focused process tests prove image/service versions, unique resource names,
  health failure, DSN injection, exit propagation, and cleanup on every exit.
- [x] Contributor documentation names the fast and complete commands and states
  that unavailable complete-suite infrastructure is a failure, never a skip.

## Out of Scope

- Persistent Docker Compose databases or manually managed local services.
- Changing Event Store, checkpoint, cursor, or failure-recorder behavior.
- The complete shared quality-gate and dependency-mode implementation owned by
  T-00028 through T-00031; those tickets compose this database lifecycle rather
  than redefining it.
- Weakening CI coverage or accepting a skip allowlist.

## Outcome

Added the repository-owned complete `bin/phpunit` lifecycle with uniquely named
MySQL 8.4.11 and PostgreSQL 17 services, health waits, DSN injection, scoped
cleanup, and fail-on-skipped PHPUnit execution. Added the explicit `--fast`
workflow, grouped the four server-database contract classes without skips, and
aligned CI and contributor documentation with the shared skip-free policy.

Focused lifecycle tests prove versions, resource uniqueness, health failure,
DSN injection, exit propagation, cleanup, and interruption. The complete suite
passed 2,958 tests and 5,048 assertions with zero skips; production Clover
coverage is exact at 8,154/8,154 statements.
