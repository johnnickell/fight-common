---
id: TICKET-00053
epic: EPIC-00013
title: Customize Event Sourcing persistence tables
status: ready-for-agent
---

# Customize Event Sourcing persistence tables

## Problem statement

Consumer integrators cannot select names for the reference Event Sourcing tables without changing package source.
Changing schema definitions alone is insufficient: append serialization, reads, checkpoints, publication cursors
and failure recording also embed the default names.

## Solution and boundaries

Deliver the Event Sourcing portion of [EPIC-00013](../epics/00013-EPIC.md). The consumer explicitly supplies
individual table-name overrides while composing adapters and schema installers. Omitted overrides preserve
these exact defaults:

| Responsibility | Default table |
| --- | --- |
| Stored events | `event_store_events` |
| Serialized global position | `event_store_global_position` |
| Projection checkpoints | `projection_checkpoints` |
| Publication cursors | `publication_cursors` |
| Publication failures | `publication_failures` |
| Failed publication handlers | `publication_handler_failures` |

Every schema definition, installation/validation step, runtime query, insert/update and locking operation for a
capability must agree on its selected names. Foreign-key relationships and associated schema objects must remain
valid. No runtime path may silently substitute the default name for a configured one.

Preserve the Event Store's stream identity, expected-version checks, exact-retry behavior, metadata snapshots and
prefix-stable committed global order. Preserve projection checkpoint monotonicity/reset and publication cursor,
failure recording and at-least-once semantics under [ADR 0001](../adr/0001-event-sourcing-contract.md) and
[ADR 0002](../adr/0002-projection-delivery.md). This changes physical naming, not stored event aliases, stream names,
projector/publication identities or event schemas.

## Use cases

The actor is the consumer integrator/operator composing persistence, followed by existing application/worker
callers. Operation labels are existing responsibilities, not new PHP APIs or bus messages.

| Use case and trigger | Commands | Queries | Events | Expected side effects |
| --- | --- | --- | --- | --- |
| Integrator selects all or some names and operator explicitly installs/validates the selected schema | N/A: composition/deployment operation, not a bus Command | Existing schema inspection | None: configuration is not a domain fact | Create/initialize only explicitly selected storage through the existing installer; validate existing selected tables |
| Application saves and reloads event history | Existing aggregate save/append path; no new Command | Existing stream/global reads | Existing recorded/published events, unchanged | Append and serialize positions using configured event and sequence tables |
| Worker projects or publishes committed events | Existing runner/checkpoint/cursor/failure operations; no new Command | Existing global polling and checkpoint/cursor reads | Existing publication dispatch, unchanged | Update configured checkpoints/cursors and record failures in configured parent/handler tables |
| Integrator changes a name or supplies an invalid one | N/A: explicit configuration | Existing inspection of the newly selected storage, if invoked | None | No rename, migration or data transfer; reject unsupported names safely |

## Validation and permissions

Accept ordinary table identifiers only through the new configuration boundary, with safe validation and
platform-appropriate quoting. Reject invalid/unsupported names rather than interpreting SQL fragments, treating
qualification as routing, or silently reverting to defaults. Keep data values parameterized. Exact grammar,
limits, collision checks and configuration/quoting APIs are downstream design within the EPIC's boundaries.

Names are trusted consumer composition inputs, not request parameters or credentials. Existing database privileges
and application authority remain consumer-owned; no new permission or authentication surface is introduced.
Preserve documented transaction, storage failure and recovery behavior, including uncertainty after a lost commit
acknowledgment. A different configured table does not imply old records were moved or successfully recovered.

## Dependencies and exclusions

Coordinate consistent naming/validation conventions with [TICKET-00054](00054-TICKET.md) and
[TICKET-00055](00055-TICKET.md); these requirement areas have no automatic sequential implementation dependency.
Any shared mechanism and technical dependency belong in TASK planning, not a separate interface-only deliverable.
The existing ORM mapping contract remains intact; TICKET-00055 owns the finite nonce/audit compatibility check.

No migrations, automatic renames/copies, implicit environment/global configuration, mandatory prefix, qualified
names, column changes, replay workflow, new event schemas, changed delivery guarantees or unrelated refactoring.
Do not impose new restrictions on existing released ORM configuration. Framework/provider-owned storage remains
outside Common's table-naming ownership.

## Acceptance and evidence

- Prove independent overrides for all six tables, partial overrides and unchanged default composition. Preserve
  released construction/named-argument and persisted-data promises under [ADR 0011](../adr/0011-non-structural-compatibility-policy.md).
- Exercise real schema installation and validation followed by append/reload/global reads, checkpoint operations,
  cursor updates and aggregate/handler failure recording on supported database platforms.
- Demonstrate relevant concurrency/ordering/retry contracts with configured sequence and data tables, not just
  schema string assertions. Configured failures must retain their correct parent/handler relationship.
- Prove configured paths do not touch default storage, including when default tables also exist; protect their
  data from accidental writes. Reject invalid/unsupported identifiers safely without SQL interpretation or fallback.
- Confirm changing configuration performs no migration/rename/data movement and that missing or incompatible
  selected storage retains explicit existing installation/failure boundaries.
- Document composition, complete defaults, partial overrides, validation limits and no-migration consequences;
  align affected component documentation and CONTEXT.md when implemented.

Each future TASK owns its applicable product evidence, exact owned-code coverage, full local gate and independent
Review/QA under the [project profile](../agents/project-profile.md). No new release-process tests or certification
are requested. These are requirements, not claims that implementation or verification has occurred.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00194](../tasks/00194-TASK.md) | Configure Event Store and sequence table names | done |
| [TASK-00195](../tasks/00195-TASK.md) | Configure projection checkpoint storage | done |
| [TASK-00196](../tasks/00196-TASK.md) | Configure publication storage and complete the customized journey | in-progress |
<!-- /planning:children -->

## Decisions and progress

The following planning checkpoints preserve their original scope; they do not describe current implementation
or publication state. The current implementation checkpoint follows them below.

John approved the complete three-TICKET decomposition of EPIC-00013, including this six-table Event Sourcing
requirement area. He subsequently approved this complete three-TASK split, normally one PR per TASK:

| Accepted TASK | Delivered outcome | Blockers |
| --- | --- | --- |
| [TASK-00194](../tasks/00194-TASK.md) — Configure Event Store and sequence table names | Two configurable tables plus safe reusable Adapter identifier handling, with real append/read/locking/retry evidence | None |
| [TASK-00195](../tasks/00195-TASK.md) — Configure projection checkpoint storage | Configured checkpoint install/load/advance/reset, durable reopen and concurrent monotonicity | TASK-00194 |
| [TASK-00196](../tasks/00196-TASK.md) — Configure publication storage and complete the customized journey | Three configured publication tables, atomic/idempotent failure recording and the all-six-table public-contract journey | TASK-00194, TASK-00195 |

TASK-00194 establishes the reusable naming mechanism through working Event Store persistence, not a foundation-only
PR. TASK-00195 consumes that mechanism. TASK-00196 consumes both suppliers for real combined integration evidence;
publication storage alone does not depend on checkpoint operations. Suppliers own their complete focused/platform
proof without a reverse acceptance dependency on the final journey.

Each TASK includes coherent schema/runtime names, safe unsupported-name rejection, intentional partial defaults,
no accidental access to overridden default tables, compatibility, applicable documentation/CONTEXT.md, exact
coverage, the complete local gate and independent Review/QA. No migrations, renames or data transfers are added.
Exact APIs/grammar and implementation details remain design; no release target or global execution priority is
assigned. TASK order metadata stays unranked so this planning does not reorder the existing ranked backlog.

All three TASK records are approved and written in the user-selected isolated planning checkout. No implementation,
runtime, implementation branch, commit or publication has started under these records. At this ticket's original
TASK-planning checkpoint, TICKET-00054 and TICKET-00055 remained to decompose. Their subsequent approved splits are
now written; [EPIC-00013](../epics/00013-EPIC.md#progress-and-planning-handoff) records complete three-TICKET/nine-TASK
planning. Consult the Board for execution selection; this ticket does not assign global priority.

### Implementation acceptance checkpoint — 2026-11-05

TASK-00194 and TASK-00195 are accepted and complete; TASK-00196 remains unfinished, so this TICKET stays open.
TASK-00195 has independent Review ACCEPT and QA PASS for its named candidate; publication/final-head verification
is pending at its administrative closeout checkpoint. Its durable canonical delivery receipt is
`.runs/tasks/TASK-00195/runs/20261105-land-01/land.md`; its TASK metadata records the verified PR after creation.
The planning-checkout instruction above is historical. Consult the [Board](../tasks/BOARD.md) for current routing;
this checkpoint neither assigns another TASK nor creates a post-merge planning obligation.
