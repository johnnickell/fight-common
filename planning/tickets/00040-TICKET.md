---
id: TICKET-00040
epic: EPIC-00009
title: Record trusted execution observations
status: ready-for-agent
---

# Record trusted execution observations

## Problem statement

Consumer instrumentation needs durable execution evidence without treating logs, supplied IDs or successful
observation recording as authoritative Workflow state. Human and Agent inspection must share trustworthy saved
records rather than reconstructing history from transient notifications or unrelated stores.

## Solution and boundaries

Deliver the recording use cases of [EPIC-00009](../epics/00009-EPIC.md), including typed contracts, Command handlers
and a working reusable persistence adapter. This is not an interface-only foundation or a new Workflow engine.

- Record sessions with consumer scope/reference and observed timing/outcome; phases with session/parent links,
  performing Agent identity, name and attempt; activities with producer, kind, safe summary and occurrence/recording
  timestamps; and linked protected messages, tool arguments/results and process output with content type and
  capture-completeness information. Performing Agent, model and instrumentation producer are distinct identities.
- Validate bounded namespaced extensions without allowing them to bypass protected-content separation. Consumers
  own their vocabulary, capture choices and pre-submission redaction. Common is not a universal secret detector.
- Preserve measured usage with units and provenance. Unknown outcomes, partial capture and absent measurements
  remain explicit; missing values are not zero. Observation facts do not confer review acceptance or execution authority.
- Version saved structured payloads and reject unsupported versions clearly rather than silently reinterpreting them.
  Compatibility is against tagged releases, not prior development commits. Under John's TASK-00148 amendment,
  unreleased session/phase/activity/usage is one provisional version-one design without historical readers/upgrades.
- Use the existing void `CommandBus::execute()` contract. Success follows commit of all related database writes,
  including database-backed content/link changes; failure before commit rolls them back together. This does not
  create a transaction with external files or the work being observed. Storage errors are explicit; a timeout can
  leave the caller uncertain whether commit occurred.
- Same producer-scoped observation identity and content deduplicate; different content conflicts rather than
  overwriting. Preserve immutable recording time on retries. The guarantee lasts only while the required identity
  and content-comparison records remain stored. Deliberate deletion does not imply indefinite retry protection.
- Preserve occurrence time separately from immutable server receipt/recording time. Qualify supported microsecond
  precision without inventing source precision. Store the full observation UUID COMB so downstream database and
  application ordering can agree. Recording time is neither commit order nor causal order.
- Supply logical FK-linked session/phase/detail separation and referential integrity so protected details can later
  be removed independently. This selects neither an exact three-table schema nor database storage for every byte.
- Publish safe observation facts through `EventDispatcher::trigger()`. Post-commit dispatch may be lost; a dispatch
  or subscriber failure cannot undo committed data and must remain distinguishable from storage rollback. Event
  payloads must not expose protected bodies. No mandatory outbox, event-sourced model or delivery guarantee follows.

## Use cases

Operation labels below describe responsibilities, not final PHP class names or an exact event catalog.

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Trusted instrumentation records session, phase and activity observations | Record observations through the Command bus | Internal reference checks; inspection belongs to TICKET-00041 | Safe committed observation facts | Atomically persist the Command's related records after admission |
| Trusted instrumentation submits linked protected content | Record observations with protected detail | Internal relationship checks; content reads belong to TICKET-00042 | Safe references/facts only, never raw bodies | Persist separately protected data and related database links atomically |
| Producer retries after an uncertain acknowledgment | Repeat the recording Command with the same producer-scoped identity | No separate public Query required for deduplication | No guaranteed event replay; exact retry dispatch behavior must be documented | Same content creates no duplicate; changed content conflicts |
| Storage or post-commit event delivery fails | Same recording operation | Persisted state can be inspected through dependent Queries | Dispatch failure is distinct from rollback | Either no related writes commit, or committed data remains despite later delivery failure |

## Validation and permissions

Consumer-owned trusted composition identifies the producer and permitted project/execution/phase scope. IDs in a
payload, message metadata or an MCP correlation value are references, not credentials. Enforce current supplied
admission decisions and validate every cross-record relationship against that scope, not merely ID existence.

Reject invalid structure, unsupported versions, exceeded limits, invalid relationships and retry conflicts before
acknowledging success. Structural validation is not proof that arbitrary text contains no secrets. Keep protected
bodies out of safe summaries, extensions and events; do not leak them through public failures. Consumers decide
whether observation failure interrupts their work; mandatory audit retains its separate guarantees.

## Dependencies and exclusions

This is the recording/persistence owner for [TICKET-00041](00041-TICKET.md) and [TICKET-00042](00042-TICKET.md).
Coordinate independent detail removal and retained retry records with TICKET-00042 without allocating a second
recording implementation. Existing CQRS, transaction, identity and value contracts remain reusable; their presence
is not evidence that this new persistence contract already exists. [ADR 0001](../adr/0001-event-sourcing-contract.md)
and [ADR 0002](../adr/0002-projection-delivery.md) govern their own optional Event Store/projection capabilities;
their stream retry or global-position guarantees must not be substituted for this observation contract.

Automatic capture/redaction, hidden reasoning, an Agent directory, Workflow/status mutation, mandatory audit,
OpenTelemetry integration, guaranteed event delivery, maintenance/archival orchestration, dashboard UI and MCP
mutation Tools are excluded. Exact classes, schema/adapter choices, finite limits, comparison mechanics and safe
event catalog remain downstream design within the accepted guarantees, not permission to omit implementation.

## Acceptance and evidence

Automated evidence must demonstrate real persistence round trips and reconstruction of versioned typed records,
not only mocked storage. Cover trusted admission, bounded extensions, Agent/producer distinction, usage provenance,
project isolation, unsupported versions, invalid relationships and safe/protected-content separation. Prove atomic
related writes, rollback, commit-before-acknowledgment, explicit storage errors, same-content retries and conflicting
retries, including concurrent attempts and stable receipt timestamps. Qualify timestamp precision and full UUID
representation for the selected persistence adapter.

Distinguish committed data from later dispatch/subscriber failures and exercise the retained-record retry boundary
jointly with TICKET-00042. No test may claim that lost post-commit events are recoverable unless the chosen
composition actually provides that additional behavior. Document public contracts, limits, schema composition,
retry/transaction boundaries and failures; align CONTEXT.md when implemented. Each implementation TASK owns its
relevant behavior coverage, exact owned-code coverage and full `./bin/build` gate under the
[project profile](../agents/project-profile.md). Existing public APIs remain supported; classify additions and any
behavior changes under the accepted compatibility rules. These are future checks, not implementation evidence.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00147](../tasks/00147-TASK.md) | Record durable execution sessions and phases | done |
| [TASK-00148](../tasks/00148-TASK.md) | Record phase activity and measured usage | in-progress |
| [TASK-00149](../tasks/00149-TASK.md) | Record separately protected execution detail | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this requirement area as the first of four TICKETs for EPIC-00009. It implements the accepted
[WF-046](../wayfinder/tickets/WF-046-choose-execution-introspection-outcomes-and-ownership.md) and
[WF-047](../wayfinder/tickets/WF-047-define-safe-observation-recording-and-query-guarantees.md) boundaries, including
[Architecture's transaction/event distinction](../../docs/engineering/standards/Architecture.md#transactions-and-events).
John subsequently approved this complete three-TASK vertical implementation split and the initial persistence scope:

| Accepted TASK | Delivered outcome | Blockers |
| --- | --- | --- |
| [TASK-00147 — Record durable execution sessions and phases](../tasks/00147-TASK.md) | Typed/versioned session/phase recording, trusted admission, working DBAL persistence, transaction/retry guarantees and safe events | None |
| [TASK-00148 — Record phase activity and measured usage](../tasks/00148-TASK.md) | Activity/usage recording, identity/provenance distinctions and database/application timestamp/full UUID comparison evidence | TASK-00147 |
| [TASK-00149 — Record separately protected execution detail](../tasks/00149-TASK.md) | Protected messages/tool data/output, atomic content/link writes, safe separation and independent storage-removal/retry evidence | TASK-00147, TASK-00148 |

Use Doctrine DBAL with actual SQLite, MySQL and PostgreSQL qualification. Initially keep protected content in
separately protected database storage; no filesystem/object-storage adapter is part of these slices. These are
three complete PR-sized recording outcomes, not separate model, schema, handler or testing PRs. Each owns its
applicable validation, permissions, compatibility, documentation, exact coverage and full local build evidence.

TASK-00149 proves the storage-level detail-removal/retry boundary. TICKET-00042 retains public authorized removal
Commands, protected Queries and their integrated deletion/retry evidence; no reverse blocker or duplicate recording
implementation is introduced. Task order follows real dependencies; no global execution priority is assigned.

All three TASKs were accepted and written at the planning checkpoint; no implementation or publication was
claimed then. TASK-00147's earlier accepted candidate A and administrative closeout B remain historical checkpoints.
The ordinary aggregate-root revision is now complete: [TASK-00147's final closeout](../tasks/00147-TASK.md#ordinary-aggregate-revision-acceptance-and-administrative-closeout)
records independent Review acceptance and affected QA PASS for candidate
`08cbfb85b1cdc9453c2ac570e0a86c093a98d957`, followed by administrative closeout. Its existing
[PR #221](https://github.com/johnnickell/fight-common/pull/221) awaited human review/merge at that checkpoint.
At TASK-00148's verified Work intake, PR #221 is integrated into develop. TASK-00148 subsequently received
independent Review acceptance and QA PASS for candidate `3633b19d312d57e07a8bb4a8085d7ac00d53d3b9`, and its
administrative closeout is recorded as `done` with [PR #222](https://github.com/johnnickell/fight-common/pull/222)
open for human review. TASK-00149 is dependency-unblocked. No release or deployment is claimed. This TICKET
remains `ready-for-agent` under automatic parent rollup because TASK-00149 is unfinished. No consumer amendment
is claimed here; release target remains unassigned.

All four EPICs' TICKET decompositions are accepted and written. This was the first complete TASK decomposition;
TICKET-00041's approved three-TASK split is written as TASK-00150–00152, and TICKET-00042's complete approved
read/removal split is written as TASK-00153–00154. TICKET-00043's complete MCP split is now also accepted/written
as TASK-00155–00156, completing this EPIC's TASK decomposition. TICKET-00044's complete decomposition is also
accepted/written as TASK-00157. TICKET-00045's complete split is accepted/written as TASK-00158–00159;
TICKET-00046's complete inspection/recovery split is accepted/written as TASK-00160–00161;
TICKET-00047's complete live/inspection/optional-Prompt split is accepted/written as TASK-00162–00164.
TICKET-00048's complete optional-Mercure TASK-00165 and TICKET-00049's complete TASK-00166–00168 split are accepted/written;
TICKET-00050/00051/00052's TASK-00169/00170/00171 are complete accepted/written decompositions; all map planning is complete.
Final activation TASK-00168's lifecycle/input/cancellation blockers are fully allocated, not implemented.
The [map handoff](../wayfinder/mcp-execution-introspection-map.md#planning-handoff) now recommends Board-based execution selection after complete map-wide TASK decomposition, not automatic implementation. The broader requirement
sequence remains 00040 → 00041/00042 → 00043; unrelated MCP capabilities are not prerequisites for observation recording.

### TASK-00147 ordinary aggregate revision closeout

TASK-00147 is `done`: accepted candidate A
`08cbfb85b1cdc9453c2ac570e0a86c093a98d957` has current independent Review
acceptance and affected QA PASS. Its retained complete local gate and the
administrative A → B bridge are recorded in its completion notes; this does not
claim PR approval, merge, release, certification or deployment. This TICKET
remains `ready-for-agent` because TASK-00148 and TASK-00149 are unfinished.
TASK-00148 is now dependency-unblocked; TASK-00149 still waits for TASK-00148.

### Current TASK-00148 user correction

The prior TASK-00148 accept/PASS and `done` checkpoint above are historical. John rejected backward-compatibility
machinery for this never-released capability. TASK-00148 is reopened `in-progress` to consolidate payload/schema at
version one, remove v2 readers/upgrades and development fixtures, and retain current recording/integrity guarantees.
Fresh independent Review and affected QA are pending; PR #222 is reused without Work publication. Dependent TASKs
wait for this correction. Tagged-release guarantees are unchanged; release target remains unassigned.

Next: `/skill:review TASK-00148` in a fresh independent session after verified, committed repair.
