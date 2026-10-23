---
id: TICKET-00041
epic: EPIC-00009
title: Inspect execution summaries and activity
status: ready-for-agent
---

# Inspect execution summaries and activity

## Problem statement

Humans and authorized Agents need to understand what is happening and where execution stopped without reading
protected message, tool or process-output bodies. Page-local totals, stale projections presented as current truth
and fabricated outcomes would make this evidence misleading.

## Solution and boundaries

Deliver shared application Queries for the safe summary-to-activity journey in
[EPIC-00009](../epics/00009-EPIC.md), backed by [TICKET-00040](00040-TICKET.md)'s reusable persistence contract.
Both dashboards and MCP consume `QueryBus::fetch()`; neither owns a competing read implementation.

- Start with summary statistics for all authorized records matching the supplied selection, not just the current
  page: execution counts, known outcomes, measured usage and explicit freshness. Preserve units/provenance and
  unknown, partial and unavailable values. Do not aggregate incompatible units as if they were interchangeable.
- Return bounded paginated executions, then a selected execution's summary and phases, then selected phase activity
  with safe metadata and authorized detail references. Preserve performing Agent identity independently from model
  and recording-producer identity. A recorded phase named `review` is not proof of independent review acceptance.
- Validate supplied selection/filter fields and ordering against the actual supported Query contract. Use existing
  strict `Pagination` semantics with `page`/`per_page`; impose documented finite use-case page/response bounds.
  Pagination construction alone does not authorize a query or make an arbitrary ordering field safe for SQL.
- Each request supplies its selection independently and rechecks current access. Totals and pages may change;
  inserted/removed records can repeat or skip rows across requests. Separate summary and detail calls are not a
  shared frozen result. No remembered search, stored browsing session or cross-request snapshot is promised.
- Default observation order is immutable server receipt/recording time descending, then full observation UUID COMB
  descending. Preserve occurrence time separately and qualify actual supported microseconds. Database and
  application full-UUID comparison must agree; random suffixes resolve ties, not chronology. Receipt time is not
  commit order or causal order, and sort keys are not a safe global event checkpoint.
- If summaries use lagging projections, report freshness honestly. Neither a stale projection nor missing
  completion evidence establishes success, failure or terminal state. Direct reads and projections must preserve
  the same authorization and safe-content contract.
- Exclude raw protected content from summary, history/search and ordinary activity responses. Return no misleading
  availability claim for missing detail; consume the truthful detail states defined with TICKET-00042.

## Use cases

Operation labels describe requirements, not final PHP names.

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Human or Agent selects permitted scope and requests an overview | N/A; read only | Summarize matching executions | N/A; inspection is not a domain change | Return authorized full-selection statistics and freshness without mutation |
| Caller browses executions and selects one | N/A; read only | List executions; inspect execution summary and phases | N/A; read only | Return bounded live pages and safe execution/phase data |
| Caller inspects a phase's activity | N/A; read only | List phase activity | N/A; read only | Return safe ordered activity with permitted references, not protected bodies |
| Caller repeats a request after data or access changes | N/A; read only | Same Query with current supplied selection/access | N/A; read only | Reevaluate scope and return current results without remembered browsing state |

## Validation and permissions

Apply consumer-supplied current scope/access decisions to both aggregate calculations and row/detail-reference
selection. A guessed execution/phase ID, previous page, pagination argument or Tool availability grants no access.
Reject invalid bounds, unsupported ordering/filter fields and out-of-scope relationships safely. Do not reveal
protected bodies through errors or infer global totals from unauthorized records. Human permissions do not
implicitly authorize Agents. Consumer authentication and permission policy remain external.

## Dependencies and exclusions

Depends on TICKET-00040's admitted records and persistence. Coordinate absent-detail presentation with
[TICKET-00042](00042-TICKET.md); its protected bodies are not needed to provide safe browsing. Supplies
[TICKET-00043](00043-TICKET.md)'s MCP inspection without waiting for Prompts, Subscriptions or asynchronous Tasks.
Existing [TICKET-00032](00032-TICKET.md) supplies strict pagination construction, not this Query's limits, field
policy, authorization or database implementation. Optional Event Store projection mechanics under
[ADR 0002](../adr/0002-projection-delivery.md) are not a mandated architecture for these reads.

No mutation Commands or domain events belong to the inspection operations. Workflow transitions, protected-content
retrieval, dashboard UI, browser page resets, frozen results, cursors for application browsing, mandatory live
updates and a new permission policy are excluded. Exact Query/DTO names, supported filter fields, finite limits
and read-model mechanics are downstream design inside this accepted journey.

## Acceptance and evidence

Prove the complete safe journey against real persisted records, including multiple projects and caller-specific
permissions. Verify full-selection statistics across more than one page, unknown outcomes, missing measurements,
units/provenance and explicit freshness. Exercise empty selections, invalid pagination/filter/order values and
current access changes between requests. Demonstrate allowed live-page changes without claiming cross-request
stability. Qualify timestamp precision and full UUID ordering using tied timestamps, late arrivals and storage vs
application comparisons. Assert protected bodies never appear in safe outputs, references or public failures.

Document Query selection, limits, ordering, live pagination, freshness and non-authoritative observation meaning;
align CONTEXT.md when implemented. Each implementation TASK owns relevant behavior tests, exact owned-code
coverage and the full `./bin/build` gate under the [project profile](../agents/project-profile.md), preserving
existing public contracts and classifying additions. Tests prove Common behavior, not a deployed consumer UI.
No capability implementation or product test result is claimed by this planning record.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00150](../tasks/00150-TASK.md) | Summarize and browse authorized executions | done |
| [TASK-00151](../tasks/00151-TASK.md) | Inspect an execution summary and its phases | ready-for-agent |
| [TASK-00152](../tasks/00152-TASK.md) | Inspect phase activity and safe detail references | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this as the second of four EPIC-00009 requirement TICKETs. The accepted
[WF-047](../wayfinder/tickets/WF-047-define-safe-observation-recording-and-query-guarantees.md) live offset/limit
contract supersedes earlier frozen browsing proposals; it must not be reopened as an implementation dependency.
John subsequently approved this complete three-TASK vertical implementation split:

| Accepted TASK | Delivered outcome | Blockers |
| --- | --- | --- |
| [TASK-00150 — Summarize and browse authorized executions](../tasks/00150-TASK.md) | Full-selection statistics, bounded execution pages and reusable read admission/selection contracts | TASK-00148 |
| [TASK-00151 — Inspect an execution summary and its phases](../tasks/00151-TASK.md) | Selected safe execution summary and bounded phase Queries | TASK-00150 |
| [TASK-00152 — Inspect phase activity and safe detail references](../tasks/00152-TASK.md) | Bounded ordered activity, permitted references and truthful capture/availability metadata without bodies | TASK-00150, TASK-00149 |

The approved initial read model queries existing DBAL observation storage directly, qualified on SQLite, MySQL and
PostgreSQL. No asynchronous projection or Event Store dependency is introduced. Freshness describes persisted
evidence actually read, not proof of complete producer capture or authoritative Workflow state.

TASK-00148 transitively supplies TASK-00147's session/phase foundation. TASK-00150 establishes reusable read
contracts/admission in a working overview path. TASK-00151 and TASK-00152 are independent of each other; the latter
also needs TASK-00149's protected-detail links and capture metadata. Storage-level fixtures prove safe absent-detail
presentation; TICKET-00042 retains public protected reads/removal and integrated deletion/retry evidence without a
reverse blocker. Each TASK owns its applicable validation, permissions, database implementation, compatibility,
documentation, exact coverage and complete local build evidence. No layer-only or tests-later TASK is approved.

All three TASKs are accepted and written. No implementation, consumer amendment, commit or publication has occurred.
`ready-for-agent` records accepted planning, not completed capability evidence. No global execution priority or
worktree choice is assigned; choose the checkout/worktree explicitly before implementation. Release target is unassigned.

All map TICKET decompositions are accepted and written. TICKET-00040–00043 now have complete accepted
TASK decompositions in TASK-00147–00156, completing this EPIC's planning decomposition. TASK-00154 consumes safe
activity Queries for integrated removal evidence; no safe-read TASK acquires a reverse dependency on it.
TASK-00155 consumes these safe Queries for MCP without duplicating their policy. The
[map handoff](../wayfinder/mcp-execution-introspection-map.md#planning-handoff) now records complete map-wide TASK decomposition through TASK-00171 and recommends Board-based selection, not automatic execution. TICKET-00044's complete single-TASK
decomposition is also accepted/written as TASK-00157, independently of introspection.

TICKET-00045's complete recording/removal split is also accepted/written as TASK-00158–00159.

TICKET-00046's complete inspection/recovery split is also accepted/written as TASK-00160–00161.

Next: `/skill:next`
