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
| 1 | [TASK-00104](00104-TASK.md) | Establish MCP protocol semantics and truthful capability discovery | [TICKET-00023 — Serve a Safe Stateless MCP Endpoint](../tickets/00023-TICKET.md) | in-progress | — | — |

## Ready Frontier

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Waiting

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| 2 | [TASK-00105](00105-TASK.md) | Serve guarded stateless MCP requests through PSR HTTP | [TICKET-00023 — Serve a Safe Stateless MCP Endpoint](../tickets/00023-TICKET.md) | ready-for-agent | [TASK-00104](00104-TASK.md) | [PR #156](https://github.com/johnnickell/fight-common/pull/156) |
| 3 | [TASK-00106](00106-TASK.md) | Register and discover explicitly opted-in MCP Tools | [TICKET-00024 — Discover and Invoke Explicitly Opted-In CQRS Tools](../tickets/00024-TICKET.md) | ready-for-agent | [TASK-00104](00104-TASK.md) | — |
| 4 | [TASK-00107](00107-TASK.md) | Invoke validated CQRS MCP Tools with safe semantic output | [TICKET-00024 — Discover and Invoke Explicitly Opted-In CQRS Tools](../tickets/00024-TICKET.md) | ready-for-agent | [TASK-00106](00106-TASK.md) | — |
| 5 | [TASK-00108](00108-TASK.md) | Orchestrate request-scoped MCP progress and cooperative cancellation | [TICKET-00025 — Stream Progress and Cancel a Tool Request Across Supported Compositions](../tickets/00025-TICKET.md) | ready-for-agent | [TASK-00105](00105-TASK.md), [TASK-00107](00107-TASK.md) | — |
| 6 | [TASK-00109](00109-TASK.md) | Deliver Fight Common progressive MCP HTTP and SSE adapters | [TICKET-00025 — Stream Progress and Cancel a Tool Request Across Supported Compositions](../tickets/00025-TICKET.md) | ready-for-agent | [TASK-00108](00108-TASK.md) | — |
| 8 | [TASK-00111](00111-TASK.md) | Protect and resume ordinary MCP input_required interactions | [TICKET-00026 — Resume Protected input_required Interactions](../tickets/00026-TICKET.md) | ready-for-agent | [TASK-00107](00107-TASK.md) | — |
| 9 | [TASK-00112](00112-TASK.md) | Atomically resume destructive MCP confirmations | [TICKET-00026 — Resume Protected input_required Interactions](../tickets/00026-TICKET.md) | ready-for-agent | [TASK-00111](00111-TASK.md) | — |
| 10 | [TASK-00113](00113-TASK.md) | Compose policy-free OAuth resource-server protection for MCP | [TICKET-00027 — Protect MCP Endpoints with Reusable OAuth Resource-Server Support](../tickets/00027-TICKET.md) | ready-for-agent | [TASK-00105](00105-TASK.md) | — |

## Needs Info

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| 7 | [TASK-00110](00110-TASK.md) | Qualify progressive MCP in every supported framework starter | [TICKET-00025 — Stream Progress and Cancel a Tool Request Across Supported Compositions](../tickets/00025-TICKET.md) | needs-info | [TASK-00109](00109-TASK.md) | — |

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
