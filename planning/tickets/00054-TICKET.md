---
id: TICKET-00054
epic: EPIC-00013
title: Customize execution persistence tables
status: ready-for-agent
---

# Customize execution persistence tables

## Problem statement

Execution persistence fixes table names in schema installation, recording transactions, repositories and inspection
queries. Consumer integrators need their own physical names without breaking recording locks, cross-record joins,
protected-content separation or removal behavior.

## Solution and boundaries

Deliver the execution persistence portion of [EPIC-00013](../epics/00013-EPIC.md). Consumers explicitly supply
individual names during schema/adapter composition, with these exact defaults for omitted overrides:

| Responsibility | Default table |
| --- | --- |
| Execution schema state and serialization | `fight_execution_schema` |
| Sessions | `fight_execution_sessions` |
| Phases | `fight_execution_phases` |
| Observations | `fight_execution_observations` |
| Separately protected content | `fight_execution_contents` |

Use those selections consistently across schema definition, explicit installation/validation, transaction locking,
recording, summary/activity/detail queries, joins, protected-content lookup and removal. Preserve referential
integrity and associated schema/index validation when names differ from defaults. State/lock tables are configurable
on the same basis as record tables.

Preserve versioned saved representations, identities, scope isolation, measured-usage provenance, same-content retry
and conflicting-content rejection, transaction outcomes and safe/protected disclosure. Physical table names do not
change observation identity, project references, record revisions, Domain/Application contracts or protocol output.

## Use cases

The integrator/operator selects storage at composition/deployment; trusted instrumentation and authorized inspectors
continue using the existing execution use cases. No naming setting is exposed through MCP.

| Use case and trigger | Commands | Queries | Events | Expected side effects |
| --- | --- | --- | --- | --- |
| Integrator selects all or some names; operator explicitly installs/validates the schema | N/A: composition/deployment, not a bus Command | Existing schema inspection | None | Existing installer creates/initializes selected storage or validates it; no migration |
| Instrumentation records a session, phase, observation or protected content | Existing recording Commands | Existing relationship/retry checks | Existing safe committed facts, unchanged | Atomically persist related records under the configured state lock and tables |
| Authorized caller inspects summaries, phases, activity or protected detail | None | Existing execution inspection Queries | None | Read only the configured tables with unchanged scope/disclosure enforcement |
| Authorized caller removes selected observations or content | Existing removal Commands | Existing selection/recovery reads | Existing removal behavior, unchanged | Remove only selected records/content in configured storage while preserving current retry/relationship guarantees |
| Integrator supplies invalid names or changes a selection | N/A: configuration | No new public Query | None | Reject unsupported names safely; a new selection never transfers old data |

## Validation and permissions

Validate ordinary table identifiers at the new configuration boundary and quote them through the actual database
platform. Reject unsafe/unsupported names and schema qualification rather than evaluating SQL or silently choosing
defaults. Preserve value binding. Exact identifier grammar/limits and PHP configuration APIs remain downstream design.

Composition remains consumer-owned, with no implicit environment/global lookup. Table selections confer no recording,
inspection, protected-content or removal authority. Existing trusted producer context, current permissions and
scope checks remain unchanged; consumer database privileges are still required.

Retain partial-schema/incompatible-schema rejection, caller-transaction rejection, rollback/closure/uncertain-outcome
semantics and explicit recovery boundaries. A configuration change does not prove data absence, successful migration
or reconciled commit uncertainty. Protected content must not become visible through a differently named join.

## Dependencies and exclusions

Agree consistent configuration/validation conventions with [TICKET-00053](00053-TICKET.md) and
[TICKET-00055](00055-TICKET.md). No requirement-level sequencing between these three tickets is imposed; TASK
planning owns actual shared-mechanism dependencies and implementation order.

Coordinate with the architectural correction sequence rooted in [TASK-00183](../tasks/00183-TASK.md), notably the
already accepted transaction separation and pending aggregate/namespace work. Inspect current source at TASK
planning time; do not duplicate those refactors or invent blanket blockers for all naming work.

No migrations, automatic table/data renames or transfers, qualified names, implicit environment/global configuration,
mandatory prefixes, column changes, new capture/disclosure policy, new protocol operations or unrelated refactoring.
Existing ORM mapping customization remains intact, with its finite compatibility check owned by TICKET-00055.

## Acceptance and evidence

- Prove independent names for all five tables, partial overrides and unchanged defaults, preserving released APIs
  and stored representations under [ADR 0011](../adr/0011-non-structural-compatibility-policy.md).
- Install/validate actual configured schemas on supported platforms, then record/reload sessions, phases,
  observations and protected content through the existing application contracts.
- Exercise summary, phase, activity and detail joins plus relevant removal/recovery paths, current permissions,
  protected-content separation, retry/conflict behavior and transaction serialization using configured names.
- Demonstrate that differently named paths do not read/write default storage, including when populated default
  tables coexist. Invalid identifiers must reject without SQL interpretation or fallback.
- Preserve missing/partial/incompatible selected-schema failure behavior and prove configuration alone does not
  rename/migrate/copy data. New explicit installation remains separate from runtime operation.
- Update execution composition documentation, all defaults, partial-override examples, validation/no-migration
  limits and affected CONTEXT.md descriptions when implemented.

Each future TASK owns applicable product tests, exact owned-code coverage, the full local gate and independent
Review/QA under the [project profile](../agents/project-profile.md). No new release-process tests or certification
are requested. Requirements do not claim current implementation, passed behavior evidence or consumer deployment.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00197](../tasks/00197-TASK.md) | Record executions in configurable tables | in-progress |
| [TASK-00198](../tasks/00198-TASK.md) | Inspect and remove captured detail from configurable execution storage | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved the complete three-TICKET decomposition of EPIC-00013, including this five-table execution persistence
requirement area. He subsequently approved this complete two-TASK split, normally one PR per TASK:

| Accepted TASK | Delivered outcome | Blockers |
| --- | --- | --- |
| [TASK-00197](../tasks/00197-TASK.md) — Record executions in configurable tables | All five selected tables in schema/install/validation, recording locks and repositories; complete recording/reconstruction and protected persistence evidence | TASK-00194 |
| [TASK-00198](../tasks/00198-TASK.md) — Inspect and remove captured detail from configurable execution storage | All execution inspection/shared SQL paths, current protected-read/removal use cases and complete guarded journeys using the real configured recorder | TASK-00197 |

TASK-00197 consumes TASK-00194's shared Adapter identifier handling, not an Event Store service or new inward
configuration contract. It delivers usable recording, not merely configuration objects. TASK-00198 consumes that
actual path to finish read/removal/guarded integration, without reimplementing recording or adding protocol
operations. Suppliers own complete focused/platform acceptance with no reverse dependency on downstream journeys.

Both TASKs own independent/partial overrides, exact defaults, schema/runtime agreement, safe identifier handling,
no accidental access to overridden default tables, existing API/data/permission guarantees, relevant docs/CONTEXT.md,
exact coverage, full local verification and independent Review/QA. No migration, automatic rename/data transfer,
qualified-name expansion or unrelated refactor is added. Coordinate existing namespace work from then-current
source without blanket blockers. Exact APIs and implementation details remain downstream design.

Both TASK records are approved and written in the user-selected isolated planning checkout. At this ticket's
original TASK-planning checkpoint, TICKET-00055 remained to decompose. Its subsequent approved split is now written;
[EPIC-00013](../epics/00013-EPIC.md#progress-and-planning-handoff) records complete three-TICKET/nine-TASK planning.
Order stays unranked so this planning does not reorder existing ranked work. Release target and global execution
priority remain unassigned. No implementation, runtime, implementation branch, commit or publication occurred under
these records. Consult the Board for execution selection.

Next: `/skill:next` from the isolated planning checkout.
