---
id: TICKET-00043
epic: EPIC-00009
title: Inspect execution evidence through MCP
status: ready-for-agent
---

# Inspect execution evidence through MCP

## Problem statement

Authorized MCP clients need the same execution evidence available to human dashboards. A second adapter-owned
query implementation could diverge on totals, current permissions, protected content and missing-detail behavior,
while automatic exposure of Commands would turn introspection into an unintended mutation interface.

## Solution and boundaries

Expose the shared application inspection use cases of [EPIC-00009](../epics/00009-EPIC.md) as explicitly registered
read-only MCP Tools through the existing guarded HTTP support. John approved read-only presentation in this
four-TICKET split; recording/removal remain trusted application Commands and are not exposed by this TICKET.

- Present authorized full-selection summary statistics and freshness, bounded execution lists, selected execution
  summary/phases, phase activity and separately authorized captured details. Use
  [TICKET-00041](00041-TICKET.md) and [TICKET-00042](00042-TICKET.md)'s Queries through `QueryBus::fetch()` rather
  than direct adapter database access or duplicated policy. TICKET-00040 supplies recorded evidence for the journey.
- Register explicit Tools and metadata using the current Tool contracts. Validate both input arguments and
  structured output with bounded schemas and the existing runtime validation path. Preserve compatible text
  presentation and safe protocol failure handling through the existing responder.
- Recheck current Tool availability and underlying use-case access independently. Tool discovery, an enabled
  endpoint, transport metadata, a pagination parameter or a returned detail reference is not a content grant.
- Preserve live `page`/`per_page` application pagination, full-selection summaries, deterministic ordering,
  freshness, source precision and unknown/partial/unavailable meanings. MCP catalog pagination is a separate
  existing protocol contract and does not change application browsing semantics.
- Keep safe summary/activity outputs free of protected bodies. Only separately authorized detail Queries may
  return bounded captured message/tool/output content. Apply limits to final protocol representation as well as
  application results so encoding or text presentation cannot create an unbounded response.
- Preserve safe missing/denied/failure behavior under current MCP conventions; never publish raw storage errors,
  exception details, credentials or hidden content. Do not label a Query failure as a successful empty history.
- Compose with existing consumer-owned authentication, routes, invocation guards and business permissions.
  Introspection must work without generic Prompts, Subscriptions, asynchronous Tasks or a live dashboard.

## Use cases

Operation labels describe presentation responsibilities; exact Tool names and schemas are downstream design.

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Consumer explicitly enables introspection Tools | N/A; composition, not mutation exposure | N/A; registration does not inspect data | N/A; no observation fact | Register only supported read operations; do not activate other capabilities |
| MCP caller requests summary and browses executions | N/A; read only | Shared summary and execution-list Queries | N/A; read only | Return authorized full-selection statistics and bounded live pages |
| Caller selects an execution and phase | N/A; read only | Shared execution/phase/activity Queries | N/A; read only | Return safe evidence and references without protected bodies |
| Separately authorized caller opens captured detail | N/A; read only | Shared protected message/detail/chunk Queries | N/A; read only | Return bounded content under fresh detail permission or a truthful safe failure |
| Caller repeats a request after access or data changes | N/A; read only | Same shared Queries with current trusted context | N/A; read only | Preserve current application semantics, not cached authorization or frozen results |

## Validation and permissions

Tool argument validation is not business authorization. Propagate only explicitly mapped selection/reference
values into the shared use cases, with trusted scope/access context supplied through consumer composition rather
than accepted from untrusted arguments as authority. Reuse the existing Tool availability and safe error contracts;
do not invent a principal model or replace the consumer's permission decisions.

Prove bounded encoded results and schema conformance for successful, empty, partial and unavailable cases. Denied
access and unknown references must not reveal protected content through text companions, structured fields, errors
or discovery. Repeated calls and returned references cannot bypass current permission checks. No Commands or domain
events are applicable to these inspection Tools because they do not mutate business state.

## Dependencies and exclusions

Depends on [TICKET-00040](00040-TICKET.md)'s recording/persistence and TICKET-00041/00042's complete shared Query
contracts. Reuse existing [TICKET-00023](00023-TICKET.md) guarded HTTP and [TICKET-00024](00024-TICKET.md) explicit
Tool invocation contracts; do not build another endpoint, capability registry or CQRS bridge.

No recording/removal/status mutation Tools, arbitrary database access, new authentication policy, consumer UI,
subscriptions, Prompt/Task dependency, deferred Resource templates or automatic Agent OS adoption. Any future
mutation exposure requires separately accepted scope and the original trusted Command admission boundary.
Exact Tool names/catalog, schemas and finite protocol limits remain subsequent design within this accepted
read-only presentation. No consumer deployment or release target is selected.

## Acceptance and evidence

Demonstrate the full summary → execution → phase/activity → protected detail journey through actual package-owned
guarded HTTP composition and a real reusable persistence adapter. Seed records through trusted application
Commands, not a new recording Tool. Compare direct application and MCP outcomes for the same authorized selection,
including multiple pages, full-selection totals, units/provenance, freshness and unknown/partial states.

Cover explicit registration, malformed arguments, current Tool availability, independent detail permission,
project isolation, changed access, absent/removed content, encoded output bounds and safe storage/application
failures. Verify safe results contain no protected bodies and no inspection Tool dispatches mutation Commands.
Run without optional Subscriptions/Prompts/Tasks to demonstrate independent usability. Controlled protocol tests
prove Common behavior, not actual Agent OS/Pi/browser deployment qualification; the EPIC keeps that evidence with
consumers rather than adding a new Common acceptance gate.

Document opt-in composition, read-only Tool use, input/output limits, pagination, failures and permission seams;
align CONTEXT.md when implemented. Each implementation TASK owns relevant behavior tests, exact owned-code
coverage and the full `./bin/build` gate under the [project profile](../agents/project-profile.md), with public
compatibility classification for additions. No implementation or product test success is claimed here.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00155](../tasks/00155-TASK.md) | Inspect execution summaries and activity through MCP | in-progress |
| [TASK-00156](../tasks/00156-TASK.md) | Inspect separately protected execution detail through MCP | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this fourth requirement TICKET and its read-only MCP boundary as part of EPIC-00009's complete
four-TICKET split. It consumes the accepted
[WF-047](../wayfinder/tickets/WF-047-define-safe-observation-recording-and-query-guarantees.md) shared application/MCP
guarantees without importing Workflow mutation authority. `ready-for-agent` means accepted requirements, not an
completed capability. John subsequently approved the complete two-TASK split below; no implementation, consumer
amendments or publication have occurred.

EPIC-00010's complete requirement decomposition is now accepted as [TICKET-00044](00044-TICKET.md).
EPIC-00011's complete four-TICKET split is also approved and written as TICKET-00045–00048.
EPIC-00012's complete four-TICKET split is now approved and written as TICKET-00049–00052, completing all map
TICKET decomposition. TICKET-00040's complete three-TASK split is now accepted and written as TASK-00147–00149.
TICKET-00041's complete split is accepted/written as TASK-00150–00152; TICKET-00042's complete read/removal split is
accepted/written as TASK-00153–00154. Shared safe/protected Queries supply this MCP journey.

| Accepted TASK | Delivered outcome | Blockers |
| --- | --- | --- |
| [TASK-00155 — Inspect execution summaries and activity through MCP](../tasks/00155-TASK.md) | Explicit safe inspection Tools, shared Query/guarded HTTP journey and bounded encoded presentation | TASK-00151, TASK-00152 |
| [TASK-00156 — Inspect separately protected execution detail through MCP](../tasks/00156-TASK.md) | Separately authorized detail Tools and the complete summary-to-detail journey | TASK-00155, TASK-00153 |

Both TASKs are accepted and written. Reuse the existing HTTP/Tool contracts and SQLite/MySQL/PostgreSQL adapters;
no new endpoint, generic CQRS bridge or adapter-owned permission/query policy is added. TASK-00155 owns safe
presentation and final encoded bounds, accounting for structured content, its text companion, escaping and protocol
overhead; schema validation alone does not prove a byte limit. TASK-00156 reuses those conventions for protected
pages/chunks with separate current permission. Exact Tool names, schemas and finite limits remain implementation
details to document. Each slice owns relevant tests, compatibility, docs/CONTEXT.md, exact coverage and full local build.

TASK-00154 is not a blocker: owned storage fixtures supply removed-content protocol evidence, while the public
removal/integrated retry journey stays with that TASK. Recording/removal remain trusted application Commands, not
MCP Tools. No Prompts/Subscriptions/asynchronous Tasks or consumer-deployment prerequisite is added. No global
execution priority or worktree choice is assigned; choose main checkout or isolated worktree explicitly before
implementation. Release target remains unassigned.

All map TICKET decompositions and TICKET-00040–00043's complete TASK decompositions are accepted/written, completing
EPIC-00009's planning decomposition, not implementation. The
[map handoff](../wayfinder/mcp-execution-introspection-map.md#planning-handoff) now records complete map-wide TASK decomposition through TASK-00171 and recommends Board-based selection, not automatic execution. TICKET-00044's complete
single-TASK decomposition is also accepted/written as TASK-00157, with no dependency on these introspection Tools.

TICKET-00045's complete recording/removal split is also accepted/written as TASK-00158–00159.

TICKET-00046's complete inspection/recovery split is also accepted/written as TASK-00160–00161.

Next: `/skill:next`
