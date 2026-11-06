---
id: TICKET-00055
epic: EPIC-00013
title: Customize durable MCP lifecycle tables
status: ready-for-agent
---

# Customize durable MCP lifecycle tables

## Problem statement

Durable Tasks, retained Notices and subscriptions embed physical table names in schema management, state locking,
persistence and recovery reads. Consumer integrators must be able to select each name without losing lifecycle
integrity or changing access policy. Existing ORM-backed nonce/audit naming must remain configurable through its
native mappings rather than acquiring a second source of truth.

## Solution and boundaries

Deliver the remaining lifecycle persistence portion of [EPIC-00013](../epics/00013-EPIC.md). Explicit consumer
composition selects each table independently; omitted overrides preserve these defaults:

| Responsibility | Default table |
| --- | --- |
| Task schema state and serialization | `fight_task_state` |
| Durable Tasks | `fight_tasks` |
| Notice namespace/position/recovery state | `fight_notice_state` |
| Retained Notices | `fight_notices` |
| Notice relationships | `fight_notice_relationships` |
| Notice retry comparisons | `fight_notice_comparisons` |
| Subscription schema state and serialization | `fight_mcp_subscription_state` |
| Subscription registrations | `fight_mcp_subscriptions` |

Schema definition, explicit installation/validation, every read/write/join, state locking and cleanup/recovery must
share the chosen names within each capability. Retain referential integrity and valid schema/index checks. A state
or comparison table is not exempt from customization.

Preserve Task terminal-state integrity, committed reads, revision/input/confirmation/cancellation behavior and
retention; Notice identity, retry comparison, ordering and truthful retained-history recovery; and subscription
registration, ownership, expiry, observation/closure and cleanup behavior. Names change storage selection, not
protocol semantics, current permission checks or a handle's authority.

This ticket also owns a finite existing-behavior check for the ORM-backed `nonces` and `audit_entries` tables:
retain the current defaults and prove consumer mapping overrides are honored. ORM metadata remains their naming
authority; no competing configuration API or broader ORM redesign is required. Combined with the other two tickets,
this covers the EPIC's initial inventory of nineteen DBAL tables and two ORM-mapped tables. Recheck current source
for any additional Common-owned table before closing implementation acceptance.

## Use cases

Actors are the consumer integrator/operator and existing authorized lifecycle callers, producers and workers.
No configuration endpoint or new application workflow is introduced.

| Use case and trigger | Commands | Queries | Events | Expected side effects |
| --- | --- | --- | --- | --- |
| Integrator chooses all or some names and operator explicitly installs/validates storage | N/A: composition/deployment, not a bus Command | Existing schema inspection | None | Existing installers create/initialize selected storage or validate it; no data migration |
| Caller/producer changes or reads a durable Task | Existing Task Commands, unchanged | Existing committed status/result/input/cancellation Queries | Existing Task notifications, unchanged | Persist/read configured Task and state tables with the same atomicity and access rules |
| Producer retains Notices; caller recovers or removes retained records | Existing Notice operations; no new Command contract | Existing recovery/inspection reads | Existing safe Notice delivery, unchanged | Use configured state, record, relationship and comparison tables without weakening retries or recovery |
| Subscriber registers, renews, closes or expires a subscription | Existing registration/renewal/closure/cleanup operations | Existing subscriber reads | Existing live delivery, unchanged | Update/read configured subscription/state tables; no new delivery guarantee |
| Consumer uses existing nonce/audit ORM mapping overrides | Existing nonce consumption/purge and audit persistence | Existing audit repository Queries | None added by naming | Honor selected ORM tables through current metadata/mapping composition |
| Integrator changes a name or supplies unsupported input | N/A: configuration | No new public Query | None | Select different storage or reject safely; never rename/copy existing data |

## Validation and permissions

The new DBAL configuration boundary accepts ordinary table identifiers only, with safe validation and platform
quoting while data remains parameterized. Reject invalid/unsupported names and qualification without SQL
interpretation or fallback to defaults. Exact grammar, limits and configuration APIs remain downstream design.
Do not introduce new restrictions on existing released ORM mapping support while retaining that separate mechanism.

Consumers own composition, connection/schema selection and database privileges. Naming is not Task access,
producer authority, Notice disclosure, subscription ownership, nonce validity or audit permission. Preserve every
current supplied permission/admission decision and safe failure projection. Configuration is not untrusted MCP
input and adds no authentication/authorization surface.

Preserve partial/incompatible-schema rejection and capability transaction/recovery contracts. A different selected
table does not renew retention, resurrect deleted records, reset identities or establish successful migration.
No table selection or absent row proves cancellation, job completion or notification delivery.

## Dependencies and exclusions

Coordinate naming/validation conventions with [TICKET-00053](00053-TICKET.md) and [TICKET-00054](00054-TICKET.md),
without artificial sequential dependencies. Actual shared mechanisms and technical dependencies belong in TASK
planning. The finite ORM compatibility check belongs here, not in a new naming system or fourth requirement ticket.

Coordinate relevant current source with the correction sequence rooted in [TASK-00183](../tasks/00183-TASK.md),
including Notice repository/transaction separation and Task handler/namespace work. Do not duplicate their scopes;
TASK planning determines actual overlap and blockers from the then-current source.

Exclude migrations, automatic renames/data transfers, qualified names in the new configuration, implicit global
or environment lookup, mandatory prefixes, column/payload changes, changed permissions, protocol additions,
stronger job/delivery guarantees, framework/provider storage ownership and unrelated refactoring.

## Acceptance and evidence

- Demonstrate independent overrides for all eight DBAL tables, partial overrides, unchanged defaults and consistent
  installer/runtime names, preserving released construction and data guarantees under
  [ADR 0011](../adr/0011-non-structural-compatibility-policy.md).
- Use actual supported databases to install/validate configured schemas and exercise Task persistence/committed
  reads/removal, input/cancellation integrity, Notice retention/retry/recovery/removal and subscription
  registration/renewal/closure/expiry/read paths, including state-lock and relationship behavior.
- Prove customized operation does not read/write default storage even when populated default tables coexist.
  Preserve scope/owner isolation and reject unsafe/unsupported identifiers without SQL interpretation or fallback.
- Prove existing nonce consumption/purge and audit persistence/queries honor ORM mapping-based table overrides,
  retaining current defaults and without introducing a competing naming source.
- Confirm changing configuration performs no migration/rename/copy and keeps missing/partial/incompatible selected
  storage failures explicit. Do not substitute successful empty reads for proof of old-data recovery.
- Reconcile the full Common-owned table inventory with the other two tickets so no internal state/lock or mapped
  table is omitted. Framework/provider-owned and test-only tables keep their separate ownership.
- Document complete defaults, per-capability composition/partial overrides, native ORM customization and
  validation/no-migration boundaries; update affected component guidance and CONTEXT.md when implemented.

Each future TASK owns its applicable product evidence, exact owned-code coverage, the complete local gate and
independent Review/QA under the [project profile](../agents/project-profile.md). No new release-process testing or
certification is requested. These are future requirements, not passed evidence or deployed consumer qualification.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00199](../tasks/00199-TASK.md) | Configure durable Task storage | done |
| [TASK-00200](../tasks/00200-TASK.md) | Configure Notice persistence and recovery storage | done |
| [TASK-00201](../tasks/00201-TASK.md) | Configure subscription storage and complete live integration | done |
| [TASK-00202](../tasks/00202-TASK.md) | Qualify ORM table overrides and complete the inventory | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved the complete three-TICKET decomposition of EPIC-00013, including this eight-table lifecycle
requirement area and preservation of existing ORM mapping-based customization. He subsequently approved this
complete four-TASK split, normally one PR per TASK:

| Accepted TASK | Delivered outcome | Blockers |
| --- | --- | --- |
| [TASK-00199](../tasks/00199-TASK.md) — Configure durable Task storage | Two selected Task/state tables across installation, locking, lifecycle persistence, committed reads and removal | TASK-00194 |
| [TASK-00200](../tasks/00200-TASK.md) — Configure Notice persistence and recovery storage | Four selected Notice tables across recording, inspection, coherent recovery and exact-incarnation removal | TASK-00194, TASK-00186 |
| [TASK-00201](../tasks/00201-TASK.md) — Configure subscription storage and complete live integration | Two selected subscription/state tables across lifecycle/inspection and actual configured Task/Notice live journeys | TASK-00194, TASK-00199, TASK-00200 |
| [TASK-00202](../tasks/00202-TASK.md) — Qualify ORM table overrides and complete the inventory | Real native nonce/audit mapping/persistence qualification and complete source-to-supplier table inventory | TASK-00196, TASK-00198, TASK-00201 |

TASK-00194 supplies the shared safe Adapter identifier mechanism. TASK-00200 consumes TASK-00186's now-integrated
separated Notice repository/transaction/recovery implementations, retaining the completed dependency edge rather
than duplicating work on its former combined store. No blanket architecture blocker is added. TASK-00201 consumes actual configured Task/
Notice suppliers for combined live evidence without reverse acceptance dependencies.

TASK-00202's native ORM probe has no code dependency on the DBAL naming work; its blockers belong to final inventory
completeness. Those three capstone suppliers transitively cover all earlier naming TASKs, so redundant direct edges
are unnecessary. Existing nonce/audit metadata remains the sole authority, with no new configuration API or
retroactive native ORM restriction. The inventory initially covers nineteen DBAL and two ORM tables; actual source
and evidence govern completeness. It is not release certification or a replacement for supplier Review/QA.

Each TASK preserves exact defaults, applicable partial overrides, safe naming, no accidental default-table access,
existing contracts/permissions, relevant docs/CONTEXT.md and required local verification/independent Review/QA.
DBAL behavior uses real SQLite/MySQL/PostgreSQL evidence. No migrations, renames/data transfers, qualified-name
expansion or unrelated refactoring are authorized. Exact APIs and implementation details remain downstream design.

The following is the original planning checkpoint, not current implementation/publication state:

All four TASK records are approved and written in the user-selected isolated planning checkout. EPIC-00013 now has
complete approved decomposition into three TICKETs and nine TASKs (TASK-00194–00202). Planning completion is not
implementation completion: all implementation, runtime allocation, implementation branches, commits and publication
remain unstarted under these records. Order stays unranked; release target and global execution priority remain
unassigned. Consult the authoritative Board for execution selection rather than inferring priority from this EPIC.

Next: `/skill:next` from the isolated planning checkout.

### Durable Task supplier acceptance checkpoint — 2026-11-05

TASK-00199 has independent Review ACCEPT and required QA PASS on candidate
`3c32a1a2f3cd453328683c95112159d17919c18f`, completing its two-table durable Task supplier with required
local verification. TASK-00200/00201/00202 remain unfinished, so this TICKET and EPIC-00013 remain open.
Notice/subscription configuration, combined live integration and final ORM/inventory qualification are not
implied by Task acceptance. The planning-checkout instruction above is historical; current routing belongs
to the [Board](../tasks/BOARD.md).

Publication/final-head verification is pending at this administrative checkpoint. Current TASK-00199 delivery
results and owned-resource dispositions belong in canonical
`.runs/tasks/TASK-00199/runs/20261105-land-01/land.md` and its actual recorded PR. No merge, release,
deployment, tracked pending-merge queue or post-merge planning chore is asserted.

### Notice supplier acceptance checkpoint — 2026-11-06

TASK-00200 has independent Review v3 ACCEPT and required QA v2 complete PASS on candidate
`daafa753fd39d0ef66073c6044e4e3912d6ee1a3`, completing its four-table configured Notice supplier with required
local verification. TASK-00201/00202 remain unfinished, so this TICKET and EPIC-00013 stay open. Subscription
configuration, combined live integration and final ORM/inventory qualification are not implied by Notice
acceptance. Earlier checkpoints and reports remain historical; current execution routing belongs to the Board.

Publication/final-head verification is pending at this administrative checkpoint. Current TASK-00200 delivery
results, accepted-candidate/administrative bridge and owned-resource dispositions belong in canonical
`.runs/tasks/TASK-00200/runs/20261106-land-01/land.md` and its actual recorded PR. No merge, release,
deployment, tracked delivery queue or recurring post-merge planning chore is asserted.

### Current subscription delivery locator — 2026-11-06

TASK-00201's authorized Land continuation result is recorded in
`.runs/tasks/TASK-00201/runs/20261106-land-02/land.md` and its actual PR metadata. The checkpoint below and
first-Land receipt remain historical evidence. This parent stays open for TASK-00202's unchanged scope.

### Subscription / combined live acceptance checkpoint — 2026-11-06

TASK-00201 has independent Review v3 ACCEPT and required QA v2 complete PASS on candidate
`cf9a09792bbc2640c4c4b90c8165253816c2d695`, completing both subscription selections and actual configured
Task/Notice/subscription live composition. Required local verification passed, including exact **19,138/19,138**
statements; QA passed **55 unique fresh scenarios** and separately qualified **252 retained captures**. Canonical
reports and the historical runtime-preservation limitation remain in TASK-00201's acceptance record.
TASK-00202's final ORM/whole-package inventory qualification remains unfinished, so this TICKET and EPIC-00013
stay open. Earlier checkpoints remain historical; current routing belongs to the Board.

Publication/final-head verification is pending at this administrative checkpoint. Current TASK-00201 delivery
results, accepted-candidate/administrative bridge and owned-resource dispositions belong in canonical
`.runs/tasks/TASK-00201/runs/20261106-land-01/land.md` and its actual recorded PR. No merge, release,
deployment, tracked delivery queue or recurring post-merge planning chore is asserted.
