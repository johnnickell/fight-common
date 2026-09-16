# TASK Board

Execution view for Fight Common. TASK records own status, dependencies, priority (`order`), and PR links.
Refresh generated rows with `./bin/planning-check --write`. A PR link does not assert merge or deployment.

## What's next?

For `/ask-matt` or an unqualified "What's next?", report the current Human Action or Needs Info decision and the
active TASK. Otherwise return the first executable TASK in Ready Frontier. Do not select by ID alone.

<!-- planning:board -->
## Active Work

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Ready Frontier

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| 1 | [TASK-00104](00104-TASK.md) | Establish MCP protocol semantics and truthful capability discovery | [TICKET-00023 — Serve a Safe Stateless MCP Endpoint](../tickets/00023-TICKET.md) | ready-for-agent | — | [PR #156](https://github.com/johnnickell/fight-common/pull/156) |

## Waiting

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| 2 | [TASK-00105](00105-TASK.md) | Serve guarded stateless MCP requests through PSR HTTP | [TICKET-00023 — Serve a Safe Stateless MCP Endpoint](../tickets/00023-TICKET.md) | ready-for-agent | [TASK-00104](00104-TASK.md) | [PR #156](https://github.com/johnnickell/fight-common/pull/156) |
| 3 | [TASK-00106](00106-TASK.md) | Register and discover explicitly opted-in MCP Tools | [TICKET-00024 — Discover and Invoke Explicitly Opted-In CQRS Tools](../tickets/00024-TICKET.md) | ready-for-agent | [TASK-00104](00104-TASK.md) | — |
| 4 | [TASK-00107](00107-TASK.md) | Invoke validated CQRS MCP Tools with safe semantic output | [TICKET-00024 — Discover and Invoke Explicitly Opted-In CQRS Tools](../tickets/00024-TICKET.md) | ready-for-agent | [TASK-00106](00106-TASK.md) | — |

## Needs Info

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Human Action

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Needs Triage

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Recently Closed

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| 1 | [TASK-00103](00103-TASK.md) | Adopt the EPIC, TICKET, and TASK planning surface | — (standalone chore) | done | — | [PR #155](https://github.com/johnnickell/fight-common/pull/155) |
<!-- /planning:board -->

## Wayfinder

Consult the [map index](../wayfinder/README.md) for current charting state. A map's authored Frontier identifies
the next decision; a closed map does not create a new implementation commitment.

## History

Completed planning remains in the [TASK archive](archive/README.md). The [migration map](../MIGRATION.md) resolves
old IDs and paths. Archive records only on an explicit request.
