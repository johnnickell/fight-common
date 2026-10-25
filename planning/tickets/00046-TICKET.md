---
id: TICKET-00046
epic: EPIC-00011
title: Poll and inspect retained notices
status: ready-for-agent
---

# Poll and inspect retained notices

## Problem statement

Agents and consumer interfaces need to recover missed notices and inspect history without holding a stream open.
An empty page, a changed permission or expired history cannot honestly prove that all relevant changes have been
seen. Separate MCP and browser data paths would risk inconsistent access, filtering and recovery claims.

## Solution and boundaries

Deliver the retained-history inspection and polling journey of [EPIC-00011](../epics/00011-EPIC.md) through shared
`QueryBus::fetch()` use cases backed by [TICKET-00045](00045-TICKET.md)'s real persistence.

- Supply summary-first inspection over all currently authorized matches, then bounded notice details. Support
  selection by topic, subject Agent, intended recipient, time and notification type. Include permitted author/
  producer and causation references without conflating these relationships or inventing an audience.
- Use existing strict `Pagination` page/per-page offset/limit semantics for history inspection. Each request supplies
  its selection and current access is checked again. Totals and rows may change between requests; no frozen result,
  remembered search or browser page-reset behavior is promised. Validate allowed filter/order fields as well as bounds.
- Supply bounded polling/recovery with an explicit contract for how callers identify previously retrieved notices
  and continue through retained data. Exact recovery-position mechanics are downstream design, distinct from ordinary
  history pagination. Do not silently skip saved retained notices or claim an empty live page proves complete catch-up.
- Make complete/incomplete/uncertain recovery claims only as supported by actual retained evidence. Disclose limits
  due to expiry, removal, unavailable history, concurrent changes or changed permissions so clients can reload current
  authorized data. Recovery covers successfully recorded notices still retained, not application changes whose
  notice recording never succeeded. Never promise indefinite recovery after deletion.
- Expose polling through an explicitly registered, validated MCP Tool on the existing guarded HTTP composition.
  This is a Common Tool, not a standard `subscriptions/poll` RPC or an automatic subscription. It uses the same
  application Queries as consumer browser inspection, not direct adapter database access or duplicated policy.
- Bound selection inputs, pages, polling batches and final encoded Tool responses. Preserve structured-result and
  safe failure conventions. The consumer owns polling cadence, reload UI and processing acknowledgment; re-reading
  a notice does not imply exactly-once processing or create an acknowledgment ledger.
- Remain usable without MCP live subscriptions or Mercure. Both live paths can direct clients to this shared
  retained feed for recovery without treating their transport's last delivered message as proof of application processing.

## Use cases

Operation labels describe requirements, not final PHP/Tool names or a selected recovery-position representation.

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Authorized caller selects notice history | N/A; read only | Summarize matching notices; list retained notices | N/A; inspection is not a domain change | Return full-selection authorized statistics followed by bounded live pages |
| Caller inspects notice relationships | N/A; read only | Inspect retained notice details | N/A; read only | Return permitted subject/recipient/producer/causation metadata without protected bodies |
| Agent polls after missing live updates | N/A; read only | Shared recovery Query through an explicit MCP Tool | N/A; polling creates no receipt fact | Return bounded retained notices and truthful continuation/completeness information |
| Client cannot establish complete catch-up | N/A; consumer chooses reload behavior | Same recovery Query under current access | N/A; no implied delivery event | Report incomplete/uncertain history rather than fabricate a complete result |

## Validation and permissions

Apply consumer-supplied current scope/access decisions before computing aggregate statistics or returning rows,
references or recovery metadata. Topic names, subscriber/Agent references, intended-recipient fields and supplied
positions are not authority. Human/operator access does not automatically grant an Agent the same scope, and a
SUPER_ADMIN label is not a Common permission grant.

Validate supported filters/orderings, positive representable pagination, recovery inputs and finite result bounds.
Keep unknown/denied outcomes safe and avoid disclosing concealed history through metadata or errors. Do not turn
storage failures into a successful empty feed. MCP Tool availability is separate from use-case authorization;
arguments must not supply trusted identity/permission decisions. Catalog pagination remains a separate MCP contract.

## Dependencies and exclusions

Depends on TICKET-00045's recording, retention and removal evidence. Supplies browsing/recovery to
[TICKET-00047](00047-TICKET.md)'s MCP live path and [TICKET-00048](00048-TICKET.md)'s optional Mercure output;
its own acceptance does not require either stream. Reuse existing guarded HTTP/Tool invocation and
[TICKET-00032](00032-TICKET.md)'s strict pagination construction without treating that value object as query policy.

No mutation Commands or domain events belong to these reads. Exclude frozen browsing, browser navigation, a new
permission policy, processing acknowledgments, exactly-once handling, protected bodies, archival recovery and a
standard `subscriptions/poll` method. Exact Query/Tool names, numeric bounds, recovery metadata and concurrency
mechanics remain downstream design within the accepted behavior, not permission to weaken retained recovery.

## Acceptance and evidence

Prove summary → paginated history → detail and polling recovery against actual persisted notices across multiple
scopes, including all required relationship distinctions and selection filters. Verify full-selection statistics,
current authorization, changing live pages, bounded results and safe absent/denied outcomes. Demonstrate recovery
during retention, expiry/removal, concurrent recording/deletion and access changes without silently skipping retained
notices or treating an empty page as proof of completeness. Identify where complete catch-up cannot be established
and prove the response reports that limitation. Failure to record an originating notice remains outside recovery.

Exercise the actual guarded HTTP polling Tool with schema/argument validation, bounded encoded results and safe
failures. Compare direct Query and Tool outcomes, including permission changes, and prove use without open streams
or Mercure. Reads must not mutate business data or create delivery acknowledgments. Document limits, selection,
pagination, continuation/completeness meanings and reload responsibilities; align CONTEXT.md when implemented.
Each implementation TASK owns relevant behavior tests, exact owned-code coverage, compatibility classification
and the complete local `./bin/build` gate under the [project profile](../agents/project-profile.md). These are future
checks, not proof of a deployed browser/MCP client or implementation already accepted.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00160](../tasks/00160-TASK.md) | Summarize and inspect authorized retained notices | in-progress |
| [TASK-00161](../tasks/00161-TASK.md) | Recover retained notices through shared Queries and MCP | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this second requirement area in EPIC-00011's complete four-TICKET split. The retained-recovery scope
is the EPIC's accepted refinement of [WF-048](../wayfinder/tickets/WF-048-select-additional-mcp-capabilities-from-consumer-journeys.md),
not its historical live-only proposal. John subsequently approved the complete two-TASK decomposition, now written:

| Accepted TASK | Outcome | Blockers |
| --- | --- | --- |
| [TASK-00160](../tasks/00160-TASK.md) | Summarize and inspect authorized retained notices | TASK-00158 |
| [TASK-00161](../tasks/00161-TASK.md) | Recover retained notices through shared Queries and MCP | TASK-00160, TASK-00159 |

Initial inspection reads dedicated DBAL storage directly on SQLite/MySQL/PostgreSQL, with shared current admission,
full-selection summaries and stateless live page/per-page history/detail. Use strict pagination, explicit filter/order
allowlists and deterministic tie-breaking; count notices without recipient/reference join or retry multiplication.
TASK-00160 uses storage fixtures for disappearance and does not depend on public removal.

TASK-00161 owns shared recovery and its actual guarded HTTP polling Tool together. Approved direction: ascending
committed positions; bounded opaque continuation protected against tampering/concealed metadata disclosure; an
identified examined committed boundary; qualified complete/incomplete/uncertain outcomes; current authorization
on every call, with uncertainty/restart when access/selection continuity cannot be established. Assess rows and
history-loss evidence coherently under concurrent deletion. No empty-page proof, raw global-counter disclosure,
frozen browsing or retention lease. Bound scans, batches and actual encoded responses including structured/text
duplication, escaping and applicable metadata/envelope overhead; never advance past an omitted eligible notice.
Exact APIs, token representation, numeric limits and consistency mechanics remain documented implementation details.

Both TASKs own complete vertical implementation, permissions, compatibility, docs/CONTEXT.md, meaningful tests on
all three databases, exact coverage and full local build. No dependency on unfinished introspection/Prompts or live
MCP/Mercure is added. No global execution priority or checkout/worktree choice is assigned; choose main checkout or
isolated worktree explicitly before implementation. Release target remains unassigned. `ready-for-agent` records
accepted planning, not implementation acceptance or execution authority. No implementation, consumer changes,
commit or publication have occurred.

All map TICKET decompositions are accepted and written. TICKET-00040's complete three-TASK split is now accepted
and written as TASK-00147–00149. The
[map handoff](../wayfinder/mcp-execution-introspection-map.md#planning-handoff) now records complete map-wide TASK decomposition through TASK-00171 and recommends Board-based selection, not automatic execution. TICKET-00041's
complete split is accepted/written as TASK-00150–00152; TICKET-00042's complete split is accepted/written as
TASK-00153–00154. TICKET-00043's complete MCP split is also accepted/written as TASK-00155–00156.
TICKET-00044's complete single-TASK decomposition is accepted/written as TASK-00157.

TICKET-00045's complete recording/removal split is now accepted/written as
[TASK-00158](../tasks/00158-TASK.md) and [TASK-00159](../tasks/00159-TASK.md), the latter blocked only by the former.
They supply dedicated DBAL storage qualified on SQLite/MySQL/PostgreSQL, producer/scope-bound content comparison,
commit-ordered notice positions and atomic bounded access-safe history-loss evidence that survives an empty feed.
This TICKET consumes those storage contracts for public Queries/Tool continuation and truthful recovery after
publication failure, removal, concurrent changes or changed access. Neither storage TASK acquires a reverse blocker;
exact public recovery representation remains this TICKET's subsequent design.

TICKET-00047's complete split is now accepted/written as TASK-00162–00164. TASK-00162 consumes TASK-00161's shared
source/recovery contracts and proves actual live delivery/failure recovery; inspection/polling retain no reverse blocker.

TICKET-00048's complete optional-Mercure TASK-00165 is accepted/written, blocked only by TASK-00161. It owns actual
publisher translation/failure and shared recovery proof without hub-replay assumptions or a reverse polling dependency.

Next: `/skill:next`
