# Wayfinder Map: Reusable execution introspection and MCP evolution

**Label:** `wayfinder:map`
**Status:** Closed

> This map is an **index, not a store**. Each material decision lives in exactly one linked ticket under
> `tickets/`; this map summarizes the linked decisions and identifies the next frontier.

## Destination

Decide which reusable capabilities Fight Common should provide for recording safe execution observations and
surfacing session/phase introspection through MCP, using fight-agent-os as the first planned consumer. Include
separately permissioned Factory-style browsing of captured messages, tool details and process output without
requiring reasoning or complete capture. Select additional MCP protocol features only when they support a named
consumer journey.

**Done** = all linked decisions are Closed, remaining fog is resolved or explicitly excluded, and approved
package destinations link to their resulting planning handoff. Consumer amendments must stay with their owners;
no Common decision can silently change Agent OS execution, privacy, authority or dashboard requirements.

**Done:** all three decisions are Closed and all four approved EPIC handoffs are written:
[Execution introspection — EPIC-00009](../epics/00009-EPIC.md),
[Generic MCP Prompts — EPIC-00010](../epics/00010-EPIC.md),
[Generic MCP Subscriptions — EPIC-00011](../epics/00011-EPIC.md) and
[Generic MCP Tasks — EPIC-00012](../epics/00012-EPIC.md). EPIC-00009 now has its complete approved requirement split
in TICKET-00040–00043, and EPIC-00010 has its complete approved single-TICKET decomposition in TICKET-00044.
EPIC-00011 has its complete approved split in TICKET-00045–00048, and EPIC-00012 now has its complete approved
split in TICKET-00049–00052. All map TICKET decomposition is accepted and written. TICKET-00040 now has its complete
approved TASK split in TASK-00147–00149; TICKET-00041's complete split is also accepted/written in TASK-00150–00152.
TICKET-00042's complete split is also accepted/written in TASK-00153–00154.
TICKET-00043's complete split is also accepted/written in TASK-00155–00156, completing EPIC-00009's TASK decomposition.
TICKET-00044's complete single-TASK decomposition is also accepted/written as TASK-00157, completing EPIC-00010's planning.
TICKET-00045's complete two-TASK split is also accepted/written as TASK-00158–00159.
TICKET-00046's complete two-TASK split is also accepted/written as TASK-00160–00161.
TICKET-00047's complete three-TASK split is also accepted/written as TASK-00162–00164.
TICKET-00048's complete single-TASK decomposition is accepted/written as TASK-00165, completing EPIC-00011's planning.
TICKET-00049's complete three-TASK split is accepted/written as TASK-00166–00168; final activation's supplier IDs are resolved.
TICKET-00050's complete single-TASK decomposition is accepted/written as TASK-00169, resolving the input supplier.
TICKET-00051's complete single-TASK decomposition is accepted/written as TASK-00170, resolving the cancellation supplier.
TICKET-00052's complete single-TASK decomposition is accepted/written as TASK-00171. All map planning is complete. Map closure records
completed decisions/EPIC handoffs, not implementation acceptance or delivery.

## Notes

- John requested investigation of a previous SoftwareFactory observatory and current fight-agent-os plans.
  The requested `~/Projects/Fight/SoftwareFactory` path is absent; the research explicitly identifies the sampled
  `fight-software-factory` checkout and the other discovered candidate rather than asserting they are the named path.
- [Research: reusable execution introspection and MCP opportunities](research/mcp-observability-and-protocol-opportunities.md)
  compares source, consumer planning and official MCP `2026-07-28` documentation. It is evidence, not scope approval.
- **John's accepted constraint:** CQRS implementations; paginate every large query where practical; summary
  statistics first, then paginated detail drill-downs. [WF-046](tickets/WF-046-choose-execution-introspection-outcomes-and-ownership.md)
  owns the approved human/Agent summary-to-detail journey and Common/consumer ownership split.
- Existing Tools, Resources and Skills remain the baseline. Logging, Sampling and Roots are deprecated in the
  target protocol revision; no new adoption is proposed for checklist completeness.
- Preserve the existing closed MCP maps and accepted boundaries. This map is additive exploration, not a reopening
  of earlier decisions or an instruction to start another implementation TASK.

## Decisions so far

1. **[WF-046 — Choose reusable execution introspection outcomes and ownership](tickets/WF-046-choose-execution-introspection-outcomes-and-ownership.md)
   is Closed.** John approved the human/Agent summary-to-detail journey and reusable Common CQRS implementation,
   including a persistence adapter and MCP presentation. Consumers retain Workflow authority, permissions,
   trusted producer identification, retention, database composition and UI. Existing Agent OS policy is unchanged.
2. **[WF-047 — Define safe observation recording and query guarantees](tickets/WF-047-define-safe-observation-recording-and-query-guarantees.md)
   is Closed.** John approved trusted admission, safe persistence, commit-before-acknowledgment, retry/conflict
   handling and explicit failures without Workflow authority. He also approved separately permissioned rich-detail
   browsing and summary-first pagination. His later clarification selects stateless offset/limit Queries, with
   `page`/`per_page` and page resets owned by the UI; no cursor requirement. John explicitly accepted changing pages
   and totals between requests, replacing the earlier unchanged-result promise.
   Default order is server-recorded time descending, then full UUID COMB descending, targeting microsecond timestamp
   support where available and preserving occurrence time separately. Separate FK-linked session/phase/detail
   storage permits independent detail removal. John clarified that retention eligibility, cleanup jobs, holds,
   archival and maintenance recovery belong to consumers, not Common. A typed core with bounded, namespaced
   consumer extensions is approved; phases carry the identity of the Agent doing the work. Consumers own capture
   and pre-submission redaction; Common validates bounded structure, separates protected content and applies
   consumer access decisions. OpenTelemetry integration is deferred; no SDK, exporter or mapping is required.
   Final approval covers versioned data, project isolation, per-Command database atomicity, retained-record retry
   safety and package acceptance evidence. Domain events are included and may dispatch outside the database
   transaction; no outbox or guaranteed delivery is required. John owns pending Agent OS alignment. Exact schema,
   limits, event catalog and adapter mechanics remain implementation-planning details.
3. **[WF-048 — Select additional MCP capabilities from consumer journeys](tickets/WF-048-select-additional-mcp-capabilities-from-consumer-journeys.md)
   is Closed.** John approved generic Prompts, Subscriptions and Tasks, using existing HTTP support, explicit
   capability enablement, consumer authentication/permissions and package protocol/integration evidence separate
   from real-client deployment qualification. Apps, URL-mode input, Resource templates and Completion are deferred.
   Four separate EPIC destinations are approved: Execution introspection, Generic MCP Prompts, Generic MCP
   Subscriptions and Generic MCP Tasks. Subsequent grills produced [EPIC-00009](../epics/00009-EPIC.md),
   [EPIC-00010](../epics/00010-EPIC.md), [EPIC-00011](../epics/00011-EPIC.md) and
   [EPIC-00012](../epics/00012-EPIC.md). EPIC-00010 owns the text/Resource-link-only initial Prompt boundary.
   EPIC-00011 owns retained-notice polling recovery, live topic/Agent inspection, current subscribers,
   author/causation metadata and optional Mercure output, without guaranteed change-to-notice recording or
   per-notice subscriber history. EPIC-00012 owns durable Task lifecycle/polling plus optional authorized full-state
   notifications; recovery retrieves current state, not intermediate-notification replay.

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-046](tickets/WF-046-choose-execution-introspection-outcomes-and-ownership.md) | Choose reusable execution introspection outcomes and ownership | wayfinder:grilling | HITL | Closed | — |
| [WF-047](tickets/WF-047-define-safe-observation-recording-and-query-guarantees.md) | Define safe observation recording and query guarantees | wayfinder:grilling | HITL | Closed | [WF-046](tickets/WF-046-choose-execution-introspection-outcomes-and-ownership.md) |
| [WF-048](tickets/WF-048-select-additional-mcp-capabilities-from-consumer-journeys.md) | Select additional MCP capabilities from consumer journeys | wayfinder:research, wayfinder:grilling | HITL | Closed | [WF-046](tickets/WF-046-choose-execution-introspection-outcomes-and-ownership.md) |
<!-- /planning:decisions -->

## Blocking relationships

```text
WF-046 (Closed): first outcome and package boundary
  ├─→ WF-047 (Closed): safe CQRS recording and paginated inspection contract
  └─→ WF-048 (Closed): demand-led protocol feature selection
Decisions complete → EPIC-00009/00010/00011/00012 (written and approved)
  → TICKET decomposition (all four EPICs accepted/written: TICKET-00040–00052)
  → TASK decomposition (all thirteen TICKETs accepted/written: TASK-00147–00171)
  → Board-based execution selection (/skill:next; no automatic implementation)
```

## Frontier

None. WF-046, WF-047 and WF-048 are Closed. All four EPICs, thirteen TICKETs and 25 TASKs have complete accepted/written
planning. Follow the handoff below to Board-based execution selection, not another planning interview or automatic implementation.

## Planning handoff

[WF-048 — Approved EPIC destinations](tickets/WF-048-select-additional-mcp-capabilities-from-consumer-journeys.md#approved-epic-destinations)
owns the approved four-way split. Separate approved grill invocations produced all four records:

| Approved destination title | Resulting EPIC |
| --- | --- |
| Execution introspection | [EPIC-00009](../epics/00009-EPIC.md#progress-and-planning-handoff) — complete four-TICKET split approved and written: [TICKET-00040](../tickets/00040-TICKET.md), [TICKET-00041](../tickets/00041-TICKET.md), [TICKET-00042](../tickets/00042-TICKET.md), [TICKET-00043](../tickets/00043-TICKET.md) |
| Generic MCP Prompts | [EPIC-00010](../epics/00010-EPIC.md#progress-and-planning-handoff) — complete single-TICKET decomposition approved and written: [TICKET-00044](../tickets/00044-TICKET.md) |
| Generic MCP Subscriptions | [EPIC-00011](../epics/00011-EPIC.md#progress-and-planning-handoff) — complete four-TICKET split approved and written: [TICKET-00045](../tickets/00045-TICKET.md), [TICKET-00046](../tickets/00046-TICKET.md), [TICKET-00047](../tickets/00047-TICKET.md), [TICKET-00048](../tickets/00048-TICKET.md) |
| Generic MCP Tasks | [EPIC-00012](../epics/00012-EPIC.md#progress-and-planning-handoff) — complete four-TICKET split approved and written: [TICKET-00049](../tickets/00049-TICKET.md), [TICKET-00050](../tickets/00050-TICKET.md), [TICKET-00051](../tickets/00051-TICKET.md), [TICKET-00052](../tickets/00052-TICKET.md) |

Next: `/skill:next`

John approved EPIC-00009's four-TICKET split: recording, safe summaries/activity, protected reading/removal and
read-only MCP presentation. He also approved EPIC-00010's single requirement TICKET for authorized Prompt discovery
and retrieval, then EPIC-00011's four-TICKET split for retained recording/removal, polling/history recovery,
MCP live changes/current subscribers and optional Mercure output. He then approved EPIC-00012's four-TICKET split
for durable handles/status/results, requested input, cooperative cancellation and optional live Task state recovery.
All thirteen accepted records are written; their EPICs own the detailed requirement mapping. All map TICKET
decomposition is complete. John subsequently approved TICKET-00040's complete three-TASK split:

| Accepted TASK | Outcome | Blockers |
| --- | --- | --- |
| [TASK-00147](../tasks/00147-TASK.md) | Durable session/phase recording | None |
| [TASK-00148](../tasks/00148-TASK.md) | Phase activity and measured usage | TASK-00147 |
| [TASK-00149](../tasks/00149-TASK.md) | Separately protected execution detail | TASK-00147, TASK-00148 |

All three TASKs are written. The approved initial persistence scope uses DBAL with SQLite, MySQL and PostgreSQL
qualification and separately database-backed protected content; no filesystem/object-storage adapter is included.
Storage-level removal/retry evidence belongs to TASK-00149; public protected reads/removal and integrated deletion/
retry evidence remain with TICKET-00042. No global execution priority or worktree choice is assigned.

John subsequently approved TICKET-00041's complete three-TASK split, now written:

| Accepted TASK | Outcome | Blockers |
| --- | --- | --- |
| [TASK-00150](../tasks/00150-TASK.md) | Full-selection authorized statistics and bounded execution pages | TASK-00148 |
| [TASK-00151](../tasks/00151-TASK.md) | Selected execution summary and phases | TASK-00150 |
| [TASK-00152](../tasks/00152-TASK.md) | Phase activity and safe detail references | TASK-00150, TASK-00149 |

Initial reads query DBAL observation storage directly on SQLite, MySQL and PostgreSQL, without asynchronous
projections or an Event Store dependency. Freshness describes persisted evidence, not complete capture. TASK-00151
and TASK-00152 are independent; safe absence evidence uses storage fixtures and does not wait for TICKET-00042's
public protected reads/removal. Each vertical slice owns its applicable validation, permissions, implementation,
compatibility, documentation and full verification obligations.

John subsequently approved TICKET-00042's complete two-TASK split, now written:

| Accepted TASK | Outcome | Blockers |
| --- | --- | --- |
| [TASK-00153](../tasks/00153-TASK.md) | Separately authorized bounded captured-detail reading | TASK-00149, TASK-00150 |
| [TASK-00154](../tasks/00154-TASK.md) | Ordinary protected-detail removal with integrated protected/safe-read and retry evidence | TASK-00153, TASK-00152 |

Both reuse existing database-backed detail on SQLite, MySQL and PostgreSQL. Read/removal permissions remain
independent; neither protected reading nor safe activity browsing depends on removal. Removal owns the integrated
outcome evidence, not consumer retention eligibility, holds, jobs or archival. No filesystem adapter or MCP removal
Tool is added. Exact public names, chunk representation, finite limits and any removal-event catalog remain
implementation details to document. No global execution priority or worktree choice is assigned.

John subsequently approved TICKET-00043's complete two-TASK split, now written:

| Accepted TASK | Outcome | Blockers |
| --- | --- | --- |
| [TASK-00155](../tasks/00155-TASK.md) | Safe execution summary/activity MCP Tools with bounded encoded presentation | TASK-00151, TASK-00152 |
| [TASK-00156](../tasks/00156-TASK.md) | Separately authorized protected-detail MCP Tools and complete inspection journey | TASK-00155, TASK-00153 |

Both reuse shared Queries, existing guarded HTTP/Tool contracts and SQLite/MySQL/PostgreSQL adapters. Current Tool
availability remains independent from use-case/detail permission. Final encoded bounds include structured content,
its text companion, escaping and protocol overhead; schema validation alone is insufficient. Removed-content
protocol evidence uses owned storage fixtures without a TASK-00154 dependency; no mutation Tool is added. Exact
Tool names/schemas/limits remain implementation details to document. No new endpoint, generic CQRS bridge or
optional MCP capability dependency is introduced. Each slice owns full verification, compatibility and docs.
EPIC-00009's complete TASK decomposition is accepted/written; this is not implementation acceptance.

John subsequently approved TICKET-00044's complete single-TASK decomposition, now written as
[TASK-00157 — Discover and retrieve authorized MCP Prompts](../tasks/00157-TASK.md), with no unfinished blockers.
It delivers explicit registration and the complete authorized list/get HTTP journey together. Approved mechanics
are a bounded explicit catalog with lazy selected providers; declared string-valued arguments validated before
processing; deterministic ordering and stateless authenticated continuation with current access/stale-token rejection;
private/zero-TTL listing defaults; existing Resource metadata and bounded-result/central-metadata reuse with final
encoded-response proof. Static content needs no business Query; application-backed providers use shared Queries.
The accepted text/Resource-link content scope remains unchanged. No mandatory catalog database/template engine or
dependency on introspection/Subscriptions/Tasks is added. Exact APIs/optional metadata/limits remain implementation
details. EPIC-00010's complete TASK decomposition is accepted/written, not implemented.

John subsequently approved TICKET-00045's complete two-TASK decomposition, now written:

| Accepted TASK | Outcome | Blockers |
| --- | --- | --- |
| [TASK-00158](../tasks/00158-TASK.md) | Durable safe-notice recording | None |
| [TASK-00159](../tasks/00159-TASK.md) | Authorized bounded removal with honest history-loss evidence | TASK-00158 |

Approved mechanics use dedicated DBAL notice storage qualified on actual SQLite/MySQL/PostgreSQL; no Event Store,
projection or unfinished introspection/Prompt dependency. Producer/scope-bound identity deduplicates identical
accepted content while comparison evidence remains; changed content conflicts, and immutable recording time is
excluded from comparison. A dedicated commit-ordered position supports recovery without inferring commit order
from timestamp/UUID or ordinary auto-increment allocation. Removal atomically updates bounded access-safe history-loss
information that survives an empty feed, without a perpetual archive of deleted notice bodies. Exact schema/APIs/
limits/metadata/event catalog remain implementation details; comparison lifetime and repeat/missing/re-recording
behavior must be explicit without indefinite deduplication. Both TASKs prove storage contracts; TICKET-00046 owns
integrated public Query/polling Tool recovery, avoiding a reverse dependency. Consumer retention eligibility/duration,
holds/scheduling/archival remain outside Common. No global execution priority or worktree choice is assigned.

John subsequently approved TICKET-00046's complete two-TASK decomposition, now written:

| Accepted TASK | Outcome | Blockers |
| --- | --- | --- |
| [TASK-00160](../tasks/00160-TASK.md) | Full-selection authorized summaries and live history/detail | TASK-00158 |
| [TASK-00161](../tasks/00161-TASK.md) | Shared recovery Query and complete MCP polling journey | TASK-00160, TASK-00159 |

Approved initial inspection reads DBAL directly on SQLite/MySQL/PostgreSQL, with shared current admission, strict
page/per-page pagination, explicit filter/order allowlists and deterministic tie-breaking. Totals cover all authorized
matches without join/retry multiplication. TASK-00160 uses storage disappearance fixtures without a removal blocker.
TASK-00161 separately uses ascending committed positions, bounded opaque continuation protected against tampering/
concealed disclosure, an identified examined committed boundary and qualified complete/incomplete/uncertain outcomes.
Reauthorize every call; unproven selection/access continuity requires uncertainty/restart. Assess rows and history-loss
evidence coherently under deletion; empty pages alone are not proof. Bound scans/batches/final encoded output, including
structured/text duplication and applicable metadata/envelope overhead, without advancing past omitted eligible notices.
No raw global-counter disclosure, frozen results, retention lease, processing ledger or unfinished introspection/Prompt/
live adapter dependency is introduced. Exact APIs, token representation, limits and consistency mechanics remain
implementation details. Each slice owns full verification, compatibility and docs; no execution priority/worktree
choice is assigned. TICKET-00046's complete decomposition is accepted/written, not implemented.

John subsequently approved TICKET-00047's complete three-TASK decomposition, now written:

| Accepted TASK | Outcome | Blockers |
| --- | --- | --- |
| [TASK-00162](../tasks/00162-TASK.md) | Complete live Tool/Resource subscriptions with working tracking and recovery | TASK-00161 |
| [TASK-00163](../tasks/00163-TASK.md) | Authorized full-selection subscriber summaries and bounded pages | TASK-00162 |
| [TASK-00164](../tasks/00164-TASK.md) | Optional authorized Prompt catalog changes | TASK-00162, TASK-00157 |

Approved direction: cross-process bounded source reads through shared retained-notice Queries, not an in-process-only
listener or mandatory broker/Mercure/outbox. Common owns actual DBAL technical registrations on SQLite/MySQL/PostgreSQL,
with owner-bound heartbeat/freshness expiry and bounded cleanup; late updates cannot revive closed registrations.
Freshness is observation, not a permission lease or receipt. Independent inspection uses full-selection authorized
summaries and live page/per-page reads with distinct requested/accepted filters and honest scope/uncertainty. Finite
waits and transport keepalives enable idle-disconnect observation without new MCP notification types; limits cover
queues/frames/lifetime and actual runtime backpressure/timeout boundaries. Reauthorize before emission, including
queued notices and catalog-change signals. Extend guarded/native streaming and registry flags without buffering or
weakening validation; failed source/tracking cannot look healthy. Define live starting boundaries; reconnect is a
new subscription rather than replay. Stream failure preserves retained recovery under TASK-00161.
TASK-00163 and TASK-00164 are independent. Prompt integration consumes actual TASK-00157 without constructing bodies/
invoking providers to notify or blocking ordinary Prompts and Tool/Resource streams. Each slice owns applicable
real-database/live-runtime evidence, compatibility, docs/CONTEXT.md, exact coverage and full build. Exact APIs/schemas/
limits/freshness mechanics remain documented implementation details. No execution priority/worktree choice is assigned.
TICKET-00047's complete decomposition is accepted/written, not implemented.

John subsequently approved TICKET-00048's complete single-TASK decomposition, now written as
[TASK-00165 — Publish retained safe notices through optional Mercure](../tasks/00165-TASK.md), blocked only by TASK-00161.
Approved direction reuses Publisher/PrivatePublisher and existing Mercure adapters through bounded explicit delivery
and working post-commit composition. Failed recording sends nothing; failed publication preserves committed notices
and qualified shared Query recovery. Repeated attempts may duplicate publication, without automatic retries/outbox,
receipt ledger or exactly-once claim. Current consumer policy controls topic, public/private mode and disclosed fields;
read access is not broadcast permission, and denied/missing private support cannot fall back to public. Bound actual
encoded payloads including escaping/multibyte data, preserving permitted identity/relationship distinctions. Wrapped
hub exception text stays private; failures remain safely observable. No adapter SQL, hub-replay inference or MCP-live
blocker; Mercure is explicitly optional. Consumers own hub/browser permission and cannot infer universal current
entitlement or retract sent bytes from a private flag. Real SQLite/MySQL/PostgreSQL recording/recovery plus actual
Common adapters with a controlled HubInterface prove the package path, not deployed browser receipt/processing.
Exact APIs/limits/mapping/failure reporting remain implementation details; the TASK owns full evidence/compatibility/
docs obligations. No execution priority/worktree choice is assigned. EPIC-00011's complete eight-TASK decomposition
is accepted/written as TASK-00158–00165, not implemented.

John subsequently approved TICKET-00049's complete three-TASK split and cross-ticket activation/evidence sequencing:

| Accepted TASK | Outcome | Blockers |
| --- | --- | --- |
| [TASK-00166](../tasks/00166-TASK.md) | Durable shared lifecycle, trusted updates and currently authorized state retrieval | None unfinished |
| [TASK-00167](../tasks/00167-TASK.md) | Bounded authorized removal and truthful shared reads | TASK-00166 |
| [TASK-00168](../tasks/00168-TASK.md) | Complete MCP activation and combined Tool-to-handle/input/cancel/reconnect journeys | TASK-00166, TASK-00169, TASK-00170 |

The released Tasks contract has one capability declaration without a polling-only setting, and cancellation requires
acknowledgment. TICKET-00050/00051 consume TASK-00166 and own reusable input/cancel behavior and method handling;
TASK-00168 consumes their real implementations and owns final activation/combined Tool-start HTTP evidence. Supplier
TASKs own focused use-case/method proof, without a reverse dependency on activation or circular evidence gate.
TASK-00169 supplies input and TASK-00170 cancellation; both are actual blockers on TASK-00168 alongside TASK-00166.
All supplier identities are resolved and TASK-00168 is ready-for-agent but still implementation-blocked. TASK-00170
consumes TASK-00169 for actual input-intent race integration without a reverse dependency.

Approved mechanics: actual DBAL lifecycle/state/result/error/input/retention storage and direct reads on SQLite/MySQL/
PostgreSQL, atomic coherent state/payload updates, commit-before-acknowledgment and independently current creation/
producer/read decisions. Explicit retry/conflict lifetime and terminal-race protection distinguish durable state from
job dispatch/execution. Bounded removal owns deletion/comparison integrity and no-resurrection races, independent of
protocol activation. TTL is duration from creation, not inactivity, deletion proof or cancellation. Protocol negotiation
precedes asynchronous effects; preserve ordinary Tool contracts, flat task creation and detailed complete get responses,
Mcp-Name/taskId mirrors and original outcome semantics. Tool isError:true is completed; failed carries a safe JSON-RPC
error. Final encoded bounds include nested results, text companions, escaping and metadata/envelope overhead; no
truncated input maps or fabricated redacted-success results. Exact APIs/schema/limits remain implementation details.
Each TASK owns applicable real DB/HTTP evidence, compatibility/docs/CONTEXT.md, exact coverage/full gate. No global
priority, worktree choice, consumer amendment or deployed client claim is made.

John approved TICKET-00050's complete single-TASK decomposition, now written as
[TASK-00169 — Supply and admit durable Task input](../tasks/00169-TASK.md), blocked only by TASK-00166. It owns the complete
retained form/confirmation/admission/method path, not an ordinary-input slice awaiting safety later. Approved direction
extends actual DBAL lifecycle storage with private validation/binding material, immutable lifetime request keys and
bounded bookkeeping. Partial maps retain unanswered requests; all active validation precedes atomic batch admission,
while unknown/answered/superseded keys are ignored under outer access/envelope/size checks. Existing exact-key response
validation is adapted deliberately without weakening ordinary stateless replay or confirmation protections.

Confirmation binds caller/Task/operation/original arguments/request/group/expiry. Partial affirmative answers authorize
no destructive continuation; every required confirmation must accept, and any refusal retires that group without
whole-Task cancellation. Failure after admission never restores consumption. Current issuance/disclosure/admission/
continuation decisions are distinct; accepted answers are available through protected shared contracts without repeat
admission or an exactly-once effect promise. Consumers own scheduling/execution/reconciliation; no second store or
cross-store atomicity is assumed. Actual tasks/update handling checks capability/parameters, delegates to shared
Commands and returns safe ack/errors without independent extension advertisement.

TASK-00169 owns real three-database use-case/method evidence and input versus producer-terminal races. TICKET-00051
owns actual cancellation-intent/input races; TASK-00168 consumes both suppliers for complete activation and combined
Tool-start HTTP evidence. No reverse activation/removal/live/Mercure blocker is added. Compatibility/docs/CONTEXT.md,
exact coverage and full gate remain owned; APIs/schema/limits are implementation details. No implementation, global
priority or worktree selection occurred. Input and cancellation blocker allocation are now complete.

John approved TICKET-00051's complete single-TASK decomposition, now written as
[TASK-00170 — Request and observe cooperative Task cancellation](../tasks/00170-TASK.md), blocked by TASK-00166 and
TASK-00169. It integrates durable bounded intent with existing lifecycle state, shared current-authority observation
across processes, actual tasks/cancel handling and input/confirmation/producer race evidence. Commit before acknowledgment;
stale updates cannot erase intent, observation does not consume it, and repeats neither renew retention nor reset
admission evidence. Known authorized terminal requests acknowledge as no-ops preserving result/error. Intent alone
creates no cancelling/cancelled state, refusal, cleared input or restored confirmation. Continuation exposes intent to
consumer stopping decisions; actual terminal winners remain terminal. Consumers own stopping/effect reconciliation and
trusted outcome reporting, not Common job-control/compensation or exactly-once effects. No network/job-control operation
spans admission; lost response/post-commit failure cannot erase intent or prove stop. Current cancellation/read/producer/
worker permissions stay separate. Existing request-lifetime cancellation and disconnect/expiry semantics remain intact.

TASK-00170 owns real three-database use-case/method and actual TASK-00169 input-intent race evidence; TASK-00168 owns
complete extension activation/guarded HTTP integration and combined Tool-start/cancel/get journeys. All its supplier
IDs are resolved, not implemented. No reverse activation/removal/live/Mercure blocker. Compatibility/schema/docs/CONTEXT.md,
exact coverage and full gate remain owned; APIs/limits/versioning remain implementation details. No global priority,
worktree choice, consumer amendment or deployed-client qualification follows.

John approved TICKET-00052's complete single-TASK decomposition, now written as
[TASK-00171 — Follow authorized Task state and recover after reconnecting](../tasks/00171-TASK.md), blocked only by
TASK-00162 and TASK-00168. It owns optional taskIds filter/first-ack/correlation integration, bounded independent-process
Task sourcing through shared Queries and an authorized current baseline including terminal Tasks. Full DetailedTask
projection matches get semantics without its envelope, truncated input or redacted-success inventions. Fresh Task-specific
checks precede queued/baseline disclosure; unsafe/unavailable/oversized output stops honestly, not partial/private output.

Comparable Task-scoped revisions in permitted namespaced metadata on get and live output bind atomically to state.
Define comparison, scope/restart/exhaustion and privacy without global counters/private-activity leakage; timestamps and
arrival order are insufficient. Conforming clients can reject stale responses; no arbitrary client-rendering or browser
policy promise. Bounded coalescing omits intermediate history. Reconnect uses get/new listen for current retained state,
not replay. Existing technical tracking preserves permitted requested/accepted context without Task bodies; full state
stays out of general notices/Mercure. Disconnect/expiry/delivery failure never cancels or rolls back durable work.

TASK-00171 owns actual HTTP frames before closure and real SQLite/MySQL/PostgreSQL independent-producer evidence, actual
input/confirmation/cancellation suppliers, baseline/read/live/reconnect races, revocation, source/tracking/transport faults,
final encoded/SSE bounds and native compatibility. No additional inspection/Prompt/removal/Mercure blocker or reverse live
dependency. Compatibility/schema/docs/CONTEXT.md, exact coverage and full gate remain implementation obligations;
APIs/limits/revision representation remain design. No deployed-client qualification, priority or worktree choice follows.

Map-wide planning is complete: four EPICs, thirteen TICKETs and 25 TASKs (00147–00171). Next consult the authoritative
Board through `/skill:next`, without invoking it automatically or starting work. Ready Frontier entries identify satisfied
implementation dependencies, not execution authority; choose checkout/worktree explicitly before implementation.

Planning order is not a forced implementation dependency: introspection works without subscriptions, and Tasks
supports polling without subscriptions. Optional live Task notifications compose with the Subscriptions capability.

TICKET-00040–00052 have complete accepted TASK decompositions; no implementation has occurred. Agent OS
alignment remains John's follow-up, not permission to edit its records. The Team Lead/team coordination possibility discussed during EPIC-00012 remains
consumer-owned future integration, not an added Common workflow engine or an approved consumer amendment.

## Planning publication checkpoint

John explicitly authorized committing these approved planning records to a feature branch, pushing it, and opening
one planning-only PR against develop. This publishes the map/research, EPIC-00009–00012, TICKET-00040–00052,
TASK-00147–00171 and their generated navigation; it is not an implementation PR for any of those TASKs. Their pr fields
remain unassigned for their future implementation PRs, and their ready-for-agent statuses remain unchanged.

Earlier statements that no commit/publication had occurred describe the pre-publication planning checkpoint, not an
ongoing prohibition or a claim about this planning PR. Independent review remains pending; human approval of planning
scope is not an independent implementation verdict. Publication grants no implementation, merge, release, deployment or
consumer-amendment authority. The actual publication identity and validation receipt belong in the PR and ignored
publication handoff rather than recursively updating every planned TASK with this planning PR.

## Delegated details and excluded uncertainty

- Actual consumer/host qualification is separate downstream evidence, not a new Common acceptance prerequisite.
  John owns Agent OS planning alignment for protected rich-detail browsing and its existing policy owners.
- WF-047 settles persistence guarantees. Exact schema, public names, event catalog, adapter mechanics, finite
  limits and performance qualification belong to subsequent implementation planning within those guarantees.
- WF-048 settles HTTP-first optional capabilities and package evidence. Exact protocol cases and client fallback
  tests belong in their requirement/implementation records, without assuming deployed client compatibility.
- A second consumer is not a prerequisite. No extra consumer-discovery decision is required to write the EPICs.
- Historical Factory checkout identity remains an explicit research sampling limit, not a handoff blocker;
  no further historical investigation is required for the accepted scope.
- Release/version/publication decisions are outside this map's planning approval.

## Out of scope

- Implementation, database migration, event capture, installing OpenTelemetry/Loki, runtime enrollment or UI work.
- Apps, URL-mode input, Resource templates and Completion in the initial scope; later selection needs a new decision.
- OpenTelemetry SDK/exporter integration and mappings in the initial capability; any later adapter needs a concrete
  consumer export requirement and a separate decision.
- Common-owned retention policy/eligibility, holds, cleanup jobs, archive verification/orchestration and maintenance
  recovery. Consumers own these workflows; Common provides storage separation and ordinary CQRS operations.
- New Agent OS planning records, changes to its existing approvals, or a competing dashboard/state store.
- Raw message/tool/output bodies in the sanitized observation ledger; automatic Agent access to privileged
  captured content or operational logs; required reasoning capture or promises of complete output capture.
- Generic status setters, permission grants, independent review claims or publication/merge/release authority.
- Reopening deprecated MCP features merely to offer every protocol capability.
