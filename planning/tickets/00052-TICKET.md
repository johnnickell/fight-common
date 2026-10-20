---
id: TICKET-00052
epic: EPIC-00012
title: Follow live Task state and recover after reconnecting
status: ready-for-agent
---

# Follow live Task state and recover after reconnecting

## Problem statement

Authorized clients need timely Task updates without constant polling, but a missed live message must not erase
current-state recovery. Full Task notifications can contain protected results, errors or input requests; permission
to follow a general topic or retained change feed cannot authorize their disclosure.

## Solution and boundaries

Deliver [EPIC-00012](../epics/00012-EPIC.md)'s explicitly approved optional live notifications using the official Tasks
extension and [TICKET-00047](00047-TICKET.md)'s generic MCP subscription mechanics.

- Enable and advertise Task notifications only in supported composition and for clients that opt into the required
  capabilities/filters. Follow the extension's Task filter and `notifications/tasks` full-state representation,
  with the base subscription acknowledgment, correlation and lifecycle rules. Do not invent an alternate replay API.
- Project the current durable Task state under its original result/error/input semantics. Apply current Task-specific
  access checks before every disclosure, including after permission changes. Generic catalog/feed permission, a
  topic match, intended recipient or an earlier successful `tasks/get` is not authority for the next payload.
- Keep full-state notifications out of the general retained notice store and indiscriminate Mercure broadcasts.
  Consumer composition may separately publish safe Task-changed references under their own access rules; such
  references neither contain the full payload nor grant later `tasks/get` access.
- Preserve durable state when publication fails or the client disconnects. Live delivery is best-effort, not a
  transaction with state persistence or evidence that the recipient received/processed the message.
- On reconnect, clients may establish a new subscription and fetch currently authorized state through `tasks/get`.
  Recovery is current state within retention, not replay of every intermediate notification. Missing/expired state
  remains unavailable rather than manufactured completion, cancellation or recovered output.
- Coordinate initial/read responses with racing live updates so older state is not presented as newer. Exact
  ordering/version/concurrency mechanics belong downstream; the package must supply a usable documented contract
  without promising full transition history or specifying browser navigation.
- Keep polling, requested input and cooperative cancellation independently usable when Subscriptions are absent.
  This integration must not make the general retained feed, Mercure or execution introspection prerequisites for
  the durable Task operations.

## Use cases

Operation labels name requirements rather than final classes or a selected ordering representation.

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Authorized caller opens an enabled Task subscription | Protocol subscription lifecycle only; no new business mutation Command | Current Task availability/access checks | Subscription acknowledgment, not a business fact | Establish the requested permitted live delivery through TICKET-00047 |
| Durable Task state changes | State mutation belongs to TICKET-00049/00050/00051 | Obtain consistent current state and fresh disclosure decision | Full-state MCP notification, not automatically a domain Event | Emit only authorized bounded Task state under proper subscription correlation |
| Notification fails or caller disconnects | No rollback Command for durable state | State remains available under current access | No receipt/processing event is inferred | Stop/fail live delivery honestly without losing stored outcome |
| Caller reconnects and retrieves state | New subscription if desired; no business mutation | Shared `tasks/get` Query | New acknowledgment/optional live notification, not an intermediate replay guarantee | Recover current retained state without presenting stale updates as newer |
| Consumer uses polling-only composition | N/A for reads; existing input/cancel Commands remain available | Shared Task Query | No live notification required | Preserve ordinary Task functionality without Subscriptions/Mercure |

## Validation and permissions

Validate extension negotiation, requested Task filters, identities, protocol metadata and finite notification/output
bounds. Apply supplied trusted caller context and current Task-specific permissions to each full-state disclosure;
subscription opening cannot freeze authorization. Consumer policy owns identities/sharing, but Common must enforce
the supplied decision rather than substitute catalog/topic membership. Protect payloads and metadata on denied,
concealed, expired and failed paths, including private errors and input content.

Reuse TICKET-00047's bounded stream/resource lifecycle, checking Task-specific payload and authorization integration.
No new business Command is needed merely to observe a Task. Existing safe lifecycle facts may trigger presentation,
but protocol notification delivery does not create a mandatory domain event catalog, Event Store, outbox, Workflow
transition or permission grant. Notification failure must not rewrite a committed Task outcome.

## Dependencies and exclusions

Depends on [TICKET-00049](00049-TICKET.md)'s durable lifecycle/read contract and
[TICKET-00047](00047-TICKET.md)'s supported subscription mechanism. Full integration evidence covers requested-input
and cancellation states supplied by [TICKET-00050](00050-TICKET.md) and [TICKET-00051](00051-TICKET.md). Those Task
operations do not depend on this live adapter. The safe retained notice feed and optional Mercure output retain their
separate [TICKET-00045](00045-TICKET.md)/[TICKET-00046](00046-TICKET.md)/[TICKET-00048](00048-TICKET.md) ownership;
this TICKET must not repurpose them as a protected full-state replay store.

Exclude intermediate-state replay, a full transition audit ledger, per-message acknowledgments, receipt/processing
proof, exactly-once or guaranteed delivery, browser UI, protected Mercure broadcast and consumer deployment changes.
Exact live/read ordering, numeric bounds and optional composition APIs are downstream design within the accepted
contract. Polling and retention behavior must remain truthful when live delivery is unavailable.

## Acceptance and evidence

Demonstrate actual live HTTP delivery, not only in-memory transcripts, for opted-in clients with supported filters,
acknowledgment-first behavior, correct correlation and bounded full-state notifications. Cover working, requested
input and terminal result/error/cancellation states from real reusable persistence and consumer fixtures. Prove
Task-specific scope isolation and fresh permission checks, including revoked access and callers permitted to see
general notices but not full Task payloads.

Race initial reads/reconnect retrieval with live state changes and show the ordering contract does not present
stale state as newer. Exercise stream/publisher failure, disconnect/reconnect, retention/expiry and optional
resubscription; recover current state through `tasks/get` without claiming intermediate replay. Verify full Task
payloads never enter the general retained feed or indiscriminate Mercure output, durable state survives delivery
failure, and all ordinary Task operations remain usable with subscriptions disabled.

Document negotiation, filters, protected disclosure, ordering, limits and recovery/failure semantics; align CONTEXT.md
when implemented. Each implementation TASK owns relevant tests, exact owned-code coverage, public compatibility
classification and the full local `./bin/build` gate under the [project profile](../agents/project-profile.md).
Package live-runtime evidence is not actual Agent OS/Pi deployment qualification. These are future acceptance
requirements; no implementation evidence is claimed here.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00171](../tasks/00171-TASK.md) | Follow authorized Task state and recover after reconnecting | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John explicitly selected optional full-state Task notifications and current-state recovery in EPIC-00012, then
approved this fourth requirement area. The [official Tasks overview](https://modelcontextprotocol.io/extensions/tasks/overview)
and [research](../wayfinder/research/mcp-prompts-skills-jobs-and-live-updates.md#4-tasks-fit-asynchronous-jobs-execution-remains-separately-owned)
provide protocol evidence. `ready-for-agent` records accepted requirements, not executable implementation work.
All map TICKET decompositions are accepted and written. TICKET-00040's complete three-TASK split is now accepted
and written as TASK-00147–00149. No implementation, consumer changes, commit or publication have occurred.
The [map handoff](../wayfinder/mcp-execution-introspection-map.md#planning-handoff) now records complete approved TASK
decomposition across the map and recommends Board-based execution selection, not automatic implementation.
TICKET-00041's complete split is accepted/written as TASK-00150–00152; TICKET-00042's complete split is
accepted/written as TASK-00153–00154. TICKET-00043's complete MCP split is also accepted/written as TASK-00155–00156.
TICKET-00044's complete single-TASK decomposition is accepted/written as TASK-00157.

TICKET-00045's complete recording/removal split is also accepted/written as TASK-00158–00159.

TICKET-00046's complete inspection/recovery split is also accepted/written as TASK-00160–00161. Its safe notice
recovery is not a protected full-Task-state replay store or a replacement for this TICKET's current-state recovery.

TICKET-00047's complete split is now accepted/written as TASK-00162–00164. TASK-00162 supplies generic live mechanics
and tracking; subscriber inspection and optional Prompt integration are separate. Task-specific full-state authorization,
payloads and current-state recovery remain here, not in that safe retained-notice feed.

TICKET-00048's complete optional-Mercure TASK-00165 is accepted/written; it excludes full Task-state delivery and
remains independent of this Task-specific live path.

TICKET-00049's complete TASK-00166–00168 split is approved/written. TASK-00166 supplies shared durable state;
TASK-00168 owns complete protocol activation/combined input-cancel evidence after actual TICKET-00050/00051 suppliers.
This TICKET later consumes those completed operations plus TASK-00162's generic live mechanics; no reverse live blocker.

TICKET-00050's input supplier TASK-00169 and TICKET-00051's cancellation supplier TASK-00170 are approved/written.
TASK-00170 consumes TASK-00166 and TASK-00169 for actual input-intent integration; TASK-00168's full blocker set is now
TASK-00166, TASK-00169, TASK-00170. Its needs-info condition is resolved, not its implementation blockers. This live
TICKET's decomposition is now complete; ordinary polling/input/cancel do not gain a reverse live dependency.

John approved the complete single-TASK decomposition, now written as
[TASK-00171 — Follow authorized Task state and recover after reconnecting](../tasks/00171-TASK.md).
Blockers are TASK-00162 and TASK-00168, supplying generic stream/tracking and complete Task operations respectively.
Input/cancellation/lifecycle arrive transitively; inspection, Prompts, removal and Mercure are not extra blockers.
Live delivery, ordering and recovery remain one complete PR, not a delivery-only slice awaiting safety later.

Approved direction: extend notifications.taskIds with acknowledgment-first accepted/requested subset and exact
subscription correlation; missing Tasks declaration rejects with -32021. No invented Task-notification capability
setting. Source accepted Task state through bounded cross-process shared Queries, not only local listeners or general
retained notices. Send a currently authorized baseline after acknowledgment, including already-terminal Tasks, then
follow changes with bounded coalescing. No every-transition/history/replay guarantee follows.

Reuse the complete authorized tasks/get DetailedTask projection, preserving original input/result/error/cancellation
semantics without copying the get envelope, truncating requests or inventing redacted success. Provide comparable
Task-scoped state revisions in protocol-compatible namespaced metadata on both reads and live output, atomically bound
to their state. Define comparison/scope/restart/exhaustion/privacy and metadata collision behavior. Neither timestamps
nor arrival order suffice; no global counters/private-activity leakage. The documented contract lets conforming clients
reject stale responses but does not control arbitrary client rendering or browser navigation.

Reauthorize immediately before disclosure, including queued/baseline payloads and protected fields. Generic notice
permission, earlier get/opening and recipient references are not grants. If permission/availability/budget prevents
complete safe output, stop affected delivery honestly without private diagnostics or partial state. Reuse existing
owned tracking and permitted requested/accepted Task metadata, never bodies in subscriber records. Bound IDs/Query work/
queues/latest snapshots/final encoded frames/lifetime, including metadata, escaping/multibyte and SSE overhead.
Reconnect uses new listen plus current tasks/get, not replay; failure/disconnect/expiry do not cancel or rewrite work.
Full state stays out of general notices and Mercure. Polling/input/cancel remain independently usable.

TASK-00171 owns actual HTTP frames before closure, real SQLite/MySQL/PostgreSQL independent-producer evidence, actual
input/confirmation/cancellation suppliers, baseline/read/live/reconnect ordering races, revocation, lifecycle/source/
transport faults and final encoded bounds. Controlled disappearance fixtures avoid a removal blocker. It owns native
stream/schema/API compatibility, docs/CONTEXT.md, exact coverage and full build; package proof is not deployed-client
qualification. Exact APIs/revision representation/limits remain design. No priority/release/worktree choice is assigned.

All map planning is accepted/written: EPIC-00009–00012, TICKET-00040–00052 and TASK-00147–00171 (25 TASKs). No implementation
or independent acceptance is implied; parent statuses stay ready-for-agent. Consult the Board next and choose checkout/
worktree explicitly before separately authorized execution.

Next: `/skill:next`
