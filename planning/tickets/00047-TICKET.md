---
id: TICKET-00047
epic: EPIC-00011
title: Follow MCP changes and inspect current subscribers
status: ready-for-agent
---

# Follow MCP changes and inspect current subscribers

## Problem statement

Clients need authorized live change notifications, and operators need an honest view of active subscriptions.
Existing request-progress SSE does not establish a long-lived subscription contract. A topic match, intended
recipient or stale connection registration must not be mistaken for authorization, successful delivery or processing.

## Solution and boundaries

Implement [EPIC-00011](../epics/00011-EPIC.md)'s MCP subscription and current-subscriber journey on the existing
guarded HTTP composition under MCP `2026-07-28`.

- An authorized client opens `subscriptions/listen` with explicit requested filters. Emit
  `notifications/subscriptions/acknowledged` first, containing only the supported subset accepted from that request.
  Never emit an unrequested notification type or acknowledge an unsupported family as available.
- Correlate the acknowledgment, subsequent notifications and graceful completion with the originating JSON-RPC
  request ID using the standard subscription metadata. Distinguish that protocol identity from retained notice
  identity and internal subscriber registration; concurrent subscriptions must not cross-deliver or share authority.
- Support the approved catalog/Resource notification cases according to actual protocol contracts: Tool, Prompt
  and Resource list changes and selected Resource updates when the corresponding capability is composed and enabled.
  Prompt list-change integration consumes TICKET-00044; ordinary Prompt retrieval remains independently usable.
  Consumer topic vocabulary does not become an invented standard filter or notification type.
- Advertise notification support only when the actual composition can honor it. Reconcile the foundation registry's
  current rejection of subscription flags with proven support, rather than disabling validation globally. Existing
  direct JSON and request-progress behavior must remain supported when subscriptions are not selected.
- Recheck current consumer authorization before each disclosure, including topic, Resource and identity metadata.
  A successful opening handshake does not freeze permissions for the stream's lifetime. Retained safe notices must
  translate into supported protocol notifications without leaking protected content or inventing application authority.
- Bound stream resources, queues and lifetime. Define observable behavior on exceeded limits, disconnects,
  cancellation, permission changes, shutdown and graceful closure. HTTP client cancellation closes its stream;
  do not introduce obsolete `resources/subscribe` or an HTTP GET subscription endpoint. Stream termination leaves
  retained notices intact, and reconnect establishes a new live subscription under fresh access checks.
- Track trusted current subscriber identities and requested topics/filters with truthful observed connection state
  and freshness. Supply authorized summary-first, bounded shared Queries for current-subscriber inspection, including
  disconnect and stale/uncertain states when abrupt process termination prevents a clean lifecycle update.
- Do not represent abandoned registrations as confidently active or subscription matches as delivery receipts.
  No historical matching-subscriber list is attached to every notice. Technical subscription lifecycle state is not
  application Workflow state, a permission grant or a saved subscription that survives disconnected transports.

## Use cases

Operation labels describe responsibilities, not exact PHP class names or a selected lifecycle storage mechanism.

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Caller opens a supported live subscription | Protocol `subscriptions/listen`; no business mutation Command | Current availability/access checks | MCP acknowledgment, not a domain Event | Establish bounded technical subscription state and emit acknowledgment first |
| Relevant safe change occurs | No new business mutation; recording belongs to TICKET-00045 | Current access/filter checks before disclosure | Requested protocol notification, distinct from originating domain events | Emit correctly correlated permitted updates |
| Caller disconnects or server closes the stream | Technical lifecycle operation; no Workflow Command | N/A except lifecycle/access checks | Protocol completion where appropriate; no implied application fact | Stop delivery, close owned resources and update observed subscriber state |
| Authorized operator inspects current subscribers | N/A; read only | Summarize/list current subscribers and requested topics/filters | N/A; inspection is not a domain change | Return bounded current, disconnected or stale/uncertain state with truthful freshness |
| Client reconnects after missing an update | New listen request; no receipt/processing Command | TICKET-00046 supplies separate retained recovery | New acknowledgment, not replay proof | Reestablish only the new authorized live subscription |

## Validation and permissions

Validate request/filter shapes, selected Resource references, IDs, notification payloads and finite encoded/queue
bounds. Consumer composition supplies authenticated subscriber identity and current permitted scope; neither a
request argument nor client-declared capability is trusted authorization. Current-subscriber metadata and summaries
require their own supplied access decisions; a SUPER_ADMIN field alone grants nothing. Live emission must not
expose protected data through unsupported types, correlation mix-ups, errors or stale authorization.

Commands/Queries for notice persistence retain their shared owners. Protocol notifications are not automatically
Fight domain events, and starting/stopping a stream does not require a new business event catalog. Any internal
lifecycle writes must preserve honest operational state without becoming a separate application authority.

## Dependencies and exclusions

Consumes [TICKET-00045](00045-TICKET.md)'s safe retained notices and
[TICKET-00046](00046-TICKET.md)'s separate recovery path. Reuse existing guarded HTTP and progressive delivery seams
under [TICKET-00023](00023-TICKET.md) and [TICKET-00025](00025-TICKET.md), but prove the new live subscription behavior
rather than treating previous request-progress evidence as sufficient. [TICKET-00044](00044-TICKET.md) supplies
Prompts when that optional family is composed; absence must yield truthful unsupported behavior, not a mandatory
Prompt dependency for other subscription families. [TICKET-00048](00048-TICKET.md)'s Mercure path is independent.

Task-specific full-state notification authorization and payloads belong to [EPIC-00012](../epics/00012-EPIC.md).
They must not be copied into the general retained safe-notice feed. This TICKET owns generic subscription mechanics,
not Task lifecycle implementation. Exclude protected bodies, per-notice historical subscriber snapshots, receipt/
processing evidence, exactly-once delivery, additional transports, consumer identity policy and dashboard UI.
Exact lifecycle storage/freshness mechanics, numeric limits and supported optional integration cases are downstream
design within the accepted scope.

## Acceptance and evidence

Demonstrate actual live HTTP behavior in package-owned tests, distinct from in-memory protocol transcripts. Prove
acknowledgment-first ordering, the supported/requested subset, correct correlation across concurrent subscriptions,
Resource/catalog notification translation and truthful flags. Test fresh authorization before disclosure, scope
isolation, denied filters/references, permission changes and absence of protected content. Cover configured limits,
disconnect/cancellation, graceful shutdown and retention of notices despite stream failure; recover those notices
through TICKET-00046 without relying on live replay.

Exercise trusted subscriber registration, summary/list Queries, observed disconnects and abrupt-termination
stale/uncertain states; no fixture may infer receipt or processing from connection status. Verify optional Prompt
list changes when composed, safe absence of unsupported families and unchanged non-subscription HTTP/progress paths.
Document supported filters, identity semantics, limits, freshness, recovery composition and failures; align CONTEXT.md
when implemented. Each implementation TASK owns applicable tests, exact owned-code coverage, compatibility
classification and the full `./bin/build` gate under the [project profile](../agents/project-profile.md). Package
live-runtime evidence is not a claim about an actual Agent OS/Pi deployment; consumer qualification is separately
owned, not a new Common acceptance prerequisite. No implementation evidence is claimed here.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00162](../tasks/00162-TASK.md) | Follow Tool and Resource changes through live MCP | in-progress |
| [TASK-00163](../tasks/00163-TASK.md) | Summarize and inspect current MCP subscribers | ready-for-agent |
| [TASK-00164](../tasks/00164-TASK.md) | Follow authorized Prompt catalog changes through MCP | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this third requirement area in EPIC-00011's four-TICKET split, keeping live lifecycle and current
subscriber inspection together. The [official subscription contract](https://modelcontextprotocol.io/specification/2026-07-28/basic/patterns/subscriptions)
and [research](../wayfinder/research/mcp-prompts-skills-jobs-and-live-updates.md#3-subscriptions-and-mercure-can-share-changes-not-wire-protocols)
support the accepted scope. The existing [ADR 0024](../adr/0024-framework-adapter-support-and-delivery-boundaries.md)
remains applicable to capability-scoped composition; the EPIC distinguishes package proof from deployment proof.
John subsequently approved the complete three-TASK decomposition, now written:

| Accepted TASK | Outcome | Blockers |
| --- | --- | --- |
| [TASK-00162](../tasks/00162-TASK.md) | Live Tool/Resource subscriptions with working technical lifecycle tracking and retained recovery | TASK-00161 |
| [TASK-00163](../tasks/00163-TASK.md) | Authorized full-selection current-subscriber summaries and bounded pages | TASK-00162 |
| [TASK-00164](../tasks/00164-TASK.md) | Optional authorized Prompt catalog-change integration | TASK-00162, TASK-00157 |

Approved live sourcing uses bounded reads through shared retained-notice Query contracts across processes, not an
in-process-only listener or a mandatory broker/Mercure/outbox. Define the live starting boundary; reconnect is a new
subscription, not historical replay. Common supplies DBAL technical registrations on SQLite/MySQL/PostgreSQL with
owner-bound heartbeat/freshness expiry and bounded cleanup. Late updates cannot revive closed registrations; freshness
is observation, not a permission lease or confirmed receipt. Public inspection reuses this tracking with independent
access, full-selection totals and live page/per-page semantics; requested/accepted filters and uncertain state stay distinct.

Finite idle waits/transport keepalives enable disconnect observation without invented MCP notifications. Bound queues,
frames and lifetime; document actual backpressure/timeout limits. Fresh authorization applies before emission, including
queued notices and catalog-change signals. Extend guarded/native streaming and truthful registry composition without
buffering or weakening validation. Source/tracking failure cannot look healthy; saved notices survive delivery failure
and qualified recovery stays with TASK-00161. Exact APIs, schemas, limits, freshness and consistency mechanics remain
documented implementation details within these guarantees.

TASK-00163 and TASK-00164 are independent. Prompt integration consumes actual TASK-00157 providers without constructing
bodies/invoking content providers to notify; ordinary Prompts and Tool/Resource live support have no reverse blocker.
Each TASK owns applicable behavior/real-database/live-runtime evidence, compatibility, docs/CONTEXT.md, exact coverage
and full local build. No global priority or checkout/worktree choice is assigned; choose main checkout or isolated
worktree explicitly before implementation. Release target remains unassigned. `ready-for-agent` means accepted planning,
not implementation acceptance or execution authority. No implementation, consumer amendment, commit or publication
has occurred. All map TICKET decompositions are accepted and written. TICKET-00040's
complete three-TASK split is now accepted and written as TASK-00147–00149. The
[map handoff](../wayfinder/mcp-execution-introspection-map.md#planning-handoff) now records complete map-wide TASK decomposition through TASK-00171 and recommends Board-based selection, not automatic execution. TICKET-00041's
complete split is accepted/written as TASK-00150–00152; TICKET-00042's complete split is accepted/written as
TASK-00153–00154. TICKET-00043's complete MCP split is also accepted/written as TASK-00155–00156.
TICKET-00044's complete single-TASK decomposition is accepted/written as TASK-00157. It deliberately leaves
Prompt list-change delivery here; ordinary Prompt retrieval has no reverse dependency on subscriptions.

TICKET-00045's complete recording/removal split is also accepted/written as TASK-00158–00159; live MCP delivery
and subscriber qualification remain here, not implied by storage-level recovery evidence.

TICKET-00046's complete inspection/recovery split is now accepted/written as
[TASK-00160](../tasks/00160-TASK.md) and [TASK-00161](../tasks/00161-TASK.md). They supply shared authorized live-history
Queries and bounded committed-position recovery/MCP polling, with protected continuation and qualified completeness.
This TICKET's live path consumes that recovery without making polling depend on subscriptions or treating a live
transport message as proof of processing. Actual live delivery and current-subscriber evidence remain here.

TICKET-00048's complete optional-Mercure TASK-00165 is accepted/written, blocked only by TASK-00161 and independent
of this MCP live path. EPIC-00011's complete eight-TASK decomposition is accepted/written, not implemented.

Next: `/skill:next`
