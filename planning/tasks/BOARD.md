# TASK Board

## Routing index

Read only current human decisions and active work below; otherwise the first ready TASK.
Use [lightweight routing](../CONVENTIONS.md#lightweight-work-routing) for validation and evidence limits.
[Wayfinder status and frontiers](../wayfinder/README.md) answer the decision-routing question.
Use the [Roadmap](../ROADMAP.md) and current handoffs for planning phases; do not infer full decomposition from closed maps.
Read the referenced current section, not the full historical record. Missing handoff/phase needs owner input.

Execution view for Fight Common. TASK records own status, dependencies, priority (`order`), and PR links.
Refresh generated rows with `./bin/planning-check --write`. A PR link does not assert merge or deployment.
[Shared handoffs](../agents/shared-workflows.md#handoffs) select the next stage from actual evidence. Implemented
TASKs awaiting independent Review/QA remain active, not done; historical terminal records retain their old meaning.

## What's next?

For `/ask-matt` or an unqualified "What's next?", report the current Human Action or Needs Info decision and the
active TASK. Otherwise return the first executable TASK in Ready Frontier. Do not select by ID alone.

<!-- planning:next -->
| Route | TASK | Title | Status | Blocked by |
|---|---|---|---|---|
| Active Work | [TASK-00233](00233-TASK.md) | Recommend Common security patches by maintained release line | in-progress | — |
<!-- /planning:next -->

This summary and the [Roadmap execution handoff](../ROADMAP.md#current-task-handoff) derive from the same TASK
records as the sections below. Inspect each selected TASK's current handoff and independent evidence to choose
its workflow stage; metadata alone does not establish Review, QA or delivery. Do not add competing current-status
prose here. Historical completion and delivery evidence remain in the owning TASK records.

<!-- planning:board -->
## Active Work

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| 1 | [TASK-00233](00233-TASK.md) | Recommend Common security patches by maintained release line | — (standalone chore) | in-progress | — | — |

## Ready Frontier

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| 1 | [TASK-00235](00235-TASK.md) | Prepare Common v1.0.1 | — (standalone bug) | ready-for-agent | — | — |
| 3 | [TASK-00236](00236-TASK.md) | Reconcile the Common 1.1 maintenance base | — (standalone chore) | ready-for-agent | — | — |

## Waiting

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| 2 | [TASK-00234](00234-TASK.md) | Align signed request body integrity checks in Common | — (standalone bug) | ready-for-agent | [TASK-00233](00233-TASK.md) | — |
| 4 | [TASK-00237](00237-TASK.md) | Prepare Common v1.1.1 | — (standalone bug) | ready-for-agent | [TASK-00235](00235-TASK.md), [TASK-00236](00236-TASK.md) | — |
| 5 | [TASK-00238](00238-TASK.md) | Prepare Common v1.2.1 | — (standalone bug) | ready-for-agent | [TASK-00235](00235-TASK.md) | — |
| 6 | [TASK-00239](00239-TASK.md) | Prepare Common v1.3.1 | — (standalone bug) | ready-for-agent | [TASK-00235](00235-TASK.md) | — |
| 7 | [TASK-00240](00240-TASK.md) | Prepare Common v1.4.3 | — (standalone bug) | ready-for-agent | [TASK-00235](00235-TASK.md) | — |
| 8 | [TASK-00241](00241-TASK.md) | Prepare Common v1.5.2 | — (standalone bug) | ready-for-agent | [TASK-00235](00235-TASK.md) | — |

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
| None | — | — | — | — | — | — |
<!-- /planning:board -->

## Wayfinder

Consult the [map index](../wayfinder/README.md) for current charting state. A map's authored Frontier identifies
the next decision; a closed map does not create a new implementation commitment.

## History

Completed planning remains in the [TASK archive](archive/README.md). The [migration map](../MIGRATION.md) resolves
old IDs and paths. Archive records only on an explicit request.
