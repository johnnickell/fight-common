---
id: TICKET-00045
epic: EPIC-00011
title: Record and retain safe change notices
status: ready-for-agent
---

# Record and retain safe change notices

## Problem statement

A disconnected subscriber or failed live publisher must not make a successfully recorded change notice disappear.
Consumers need durable safe notices with useful relationships and time-bound removal, without implying that every
application change was recorded or that anyone received or processed a notification.

## Solution and boundaries

Deliver the recording and retention mechanics of [EPIC-00011](../epics/00011-EPIC.md): typed bounded notice data,
shared Command use cases and a working reusable persistence adapter, not merely interfaces or a transient stream.

- Admit consumer-supplied notices through `CommandBus::execute()` with trusted producer and permitted scope context.
  Successful recording means durable storage; storage failure is explicit. Subsequent MCP/Mercure publication
  failure cannot erase saved notices or prevent otherwise authorized polling recovery.
- Give each stored notice a stable identity distinct from a protocol request/subscription ID, planning TASK ID or
  authoritative application state. Define retry/conflict behavior in subsequent design and test it; identity alone
  is not an exactly-once guarantee or a license to silently overwrite conflicting data.
- Preserve topic, time and notification type plus separately supplied subject Agent, intended recipients,
  author/producer and causation Command/event/action references. Broadcast notices may have no named recipient.
  Unknown relationships remain explicit rather than inferred from a topic, live connection or payload ID.
- Keep the retained feed limited to safe change notices and references. Metadata itself requires scope/access
  enforcement. No protected message/tool/output bodies, full Task state or complete MCP wire transcript belongs here.
- Provide bounded ordinary removal Commands for consumer-selected records under supplied authorization. Consumers
  choose retention duration and run cleanup; Common owns persistence integrity and truthful consequences of removal,
  not eligibility, holds, scheduling, archival or maintenance-job recovery.
- Coordinate retained/removed-history information with TICKET-00046 so recovery can state its actual limits. A
  successful removal does not establish that a client saw the notice first. Retention is finite, not a promise of
  perpetual evidence or complete application history.
- Distinguish durable recording, application causation, domain fact publication, live delivery and subscriber
  processing. Safe recording/removal fact events, where defined, use the existing event contracts; exact catalog and
  failure handling remain downstream design. A later event/publisher failure must not be represented as storage
  rollback. No outbox or atomic transaction with the consumer's originating application change is implied.

## Use cases

Operation labels describe responsibilities, not final PHP names.

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Trusted consumer composition records a safe change | Record notice | Internal scope/reference checks; public reads belong to TICKET-00046 | Safe recording facts where specified, distinct from the causation reference | Persist a stable bounded notice before successful acknowledgment |
| Producer retries a recording request | Record notice under the defined retry contract | Internal identity checks as required | No assumed event replay or guaranteed publication | Apply documented duplicate/conflict behavior without unsupported delivery claims |
| Authorized consumer operation removes retained notices | Remove selected notices | Internal selection/scope checks; inspect remaining history through TICKET-00046 | Safe removal facts where specified | Remove bounded retained data and preserve integrity/recovery-limit information |
| Live publication fails after recording | Recording is already complete; no rollback Command | Retained notice remains readable under current access | Delivery failure remains distinct from recording success | Preserve saved data for recovery; report the publication outcome honestly |

## Validation and permissions

Consumer composition supplies trusted producer identity and scope. References to Agents, recipients or causation
are not credentials; validate structure, finite limits and permitted relationships before acceptance. Reject invalid
or out-of-scope submissions without leaking concealed records. Consumers own topic vocabulary and authoritative
identity/causation meaning. Do not manufacture a subscriber audience from the supplied intended recipients.

Safe metadata selection and capture remain consumer responsibilities. Common validates bounded structure and
protected-content separation; it does not universally detect secrets in arbitrary strings. Public failures and
safe fact events must not disclose private diagnostics or protected bodies. Current authorization governs removal
independently of read permission or a consumer-selected retention cutoff.

## Dependencies and exclusions

Supplies retained notice data to [TICKET-00046](00046-TICKET.md), [TICKET-00047](00047-TICKET.md) and
[TICKET-00048](00048-TICKET.md). TICKET-00046 owns polling/history Queries and completeness reporting; coordinate
its recovery evidence with storage/removal design rather than deriving complete history from an empty query result.
Existing CQRS/persistence contracts may be reused without requiring Event Sourcing or another execution ledger.

Guaranteed application-change recording, implicit outbox, exactly-once delivery, historical subscriber snapshots,
receipt/processing proof, protected content, Workflow authority, an Agent directory, retention-policy engine,
cleanup scheduler and archive workflow are excluded. WF-047's permitted post-commit event loss is unchanged:
storing a later notice does not recover an event that never produced a notice. Exact schema, storage product,
finite limits, retry mechanics and safe event catalog remain downstream design inside this contract.

## Acceptance and evidence

Use real reusable persistence to prove admitted records survive reconstruction, retain stable identity and preserve
distinct subject/recipient/producer/causation fields, including broadcasts and unknown relationships. Cover invalid
metadata, exceeded bounds, unauthorized scopes/references, safe-content separation and explicit storage failures.
Demonstrate successful acknowledgment only after durable recording and polling recovery after live publication
failure. Exercise defined retry/conflict behavior and bounded authorized removal, including concurrent recording
and deletion with truthful remaining-history information. Distinguish persistence outcomes from later fact-event
or publisher failures; do not claim delivery or transaction guarantees absent from the implementation.

Document data/identity semantics, composition, limits, retention/removal, retry and failure behavior; align CONTEXT.md
when implemented. Each implementation TASK owns applicable behavior tests, exact owned-code coverage, public
compatibility classification and the full local `./bin/build` gate under the [project profile](../agents/project-profile.md).
These are future acceptance obligations, not checks already run or proof of consumer deployment.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00158](../tasks/00158-TASK.md) | Record durable safe change notices | done |
| [TASK-00159](../tasks/00159-TASK.md) | Remove retained notices without concealing history loss | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this first requirement area in EPIC-00011's complete four-TICKET split. It preserves the EPIC's
retained-notice refinement of [WF-048](../wayfinder/tickets/WF-048-select-additional-mcp-capabilities-from-consumer-journeys.md)
and [Architecture's transaction/event distinction](../../docs/engineering/standards/Architecture.md#transactions-and-events).
John subsequently approved the complete two-TASK decomposition, now written:

| Accepted TASK | Outcome | Blockers |
| --- | --- | --- |
| [TASK-00158](../tasks/00158-TASK.md) | Record durable safe change notices | None |
| [TASK-00159](../tasks/00159-TASK.md) | Remove retained notices without concealing history loss | TASK-00158 |

Approved initial persistence uses dedicated DBAL notice storage qualified on actual SQLite, MySQL and PostgreSQL,
without Event Store/projection or unfinished introspection/Prompt dependencies. Recording acknowledges actual commit
of related notice/relationship/comparison/position writes; unsupported enclosing transactions reject before writing.
Producer-and-scope-bound identity deduplicates identical accepted content while comparison evidence remains; changed
content conflicts, immutable server recording time is excluded from comparison, and retries recheck admission.

A dedicated commit-ordered notice position is separate from identity and occurrence time. Timestamp/UUID order and
ordinary auto-increment allocation alone do not prevent a later lower-position commit from being skipped. Existing
Event Store allocation is a precedent, not adoption of its payload/retry contract. Bounded history-loss information
updates atomically with removal, survives an empty feed and stays access-safe. Conservative incomplete/uncertain
reporting is permitted without a perpetual archive of deleted bodies. Document comparison evidence lifetime and
repeat/missing/removed-identity behavior; no indefinite deduplication or silent claim that re-recording restores the
original retained notice/position. Exact schema, metadata representation, APIs, limits and safe event catalog remain
implementation details within these guarantees.

Both TASKs own complete vertical implementation, permissions, applicable tests, compatibility, docs/CONTEXT.md,
exact coverage and full local build. Storage-level proof belongs here; TICKET-00046 owns the integrated public
Query/polling Tool recovery journey, with no reverse dependency. Consumers retain retention duration, eligibility,
holds, scheduling and archival policy. No global execution priority or checkout/worktree choice is assigned; choose
main checkout or isolated worktree explicitly before implementation. Release target remains unassigned.
`ready-for-agent` means accepted planning, not implementation acceptance or execution authority. No implementation,
consumer amendments, commit or publication have occurred.

All map TICKET decompositions are accepted and written. TICKET-00040's complete three-TASK split is now accepted
and written as TASK-00147–00149. The
[map handoff](../wayfinder/mcp-execution-introspection-map.md#planning-handoff) now records complete map-wide TASK decomposition through TASK-00171 and recommends Board-based selection, not automatic execution. TICKET-00041's
complete split is accepted/written as TASK-00150–00152; TICKET-00042's complete split is accepted/written as
TASK-00153–00154. TICKET-00043's complete MCP split is also accepted/written as TASK-00155–00156.
TICKET-00044's complete single-TASK decomposition is accepted/written as TASK-00157; this creates no dependency
on Prompt serving for retained notice recording/removal.

TICKET-00046's complete inspection/recovery split is now accepted/written as TASK-00160–00161. TASK-00160 consumes
TASK-00158 for direct DBAL summary/history/detail, without a removal dependency. TASK-00161 consumes TASK-00160/00159
for integrated public recovery and MCP polling; recording/removal retain no reverse blocker.

TICKET-00047's complete live/inspection/optional-Prompt split is also accepted/written as TASK-00162–00164;
recording/removal acquire no reverse dependency on those consumers.

TICKET-00048's complete TASK-00165 is accepted/written for optional post-commit Mercure output and shared recovery.
It consumes TASK-00161 transitively without making recording/removal depend on publication.

Next: `/skill:next`
