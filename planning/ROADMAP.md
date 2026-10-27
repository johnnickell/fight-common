# Roadmap

## Current EPICs

<!-- planning:epics -->
| EPIC ID | Title | Target | Status |
|---|---|---|---|
| [EPIC-00006](epics/00006-EPIC.md) | Reusable MCP Streamable HTTP Tool Support | next-minor | done |
| [EPIC-00007](epics/00007-EPIC.md) | Reusable MCP Resources and Structured Skills | unassigned | done |
| [EPIC-00008](epics/00008-EPIC.md) | Strengthen Value Objects and Validation Invariants | next-minor | done |
| [EPIC-00009](epics/00009-EPIC.md) | Execution introspection | unassigned | done |
| [EPIC-00010](epics/00010-EPIC.md) | Generic MCP Prompts | unassigned | done |
| [EPIC-00011](epics/00011-EPIC.md) | Generic MCP Subscriptions | unassigned | done |
| [EPIC-00012](epics/00012-EPIC.md) | Generic MCP Tasks | unassigned | ready-for-agent |
<!-- /planning:epics -->

## Planning frontier

These are decomposition actions, not executable TASKs. Inspect parent requirements and any missing decisions
before decomposition. Live and archived children both count. [Automatic parent completion](CONVENTIONS.md#automatic-parent-completion)
closes eligible parents during the child-completion operation. Execution remains on the [TASK Board](tasks/BOARD.md).

<!-- planning:frontier -->
| Parent ID | Title | Status | Planning action |
|---|---|---|---|
| None | — | — | — |
<!-- /planning:frontier -->

## Current TASK handoff

<!-- planning:execution -->
| Route | TASK | Title | Status | Blocked by |
|---|---|---|---|---|
| Active Work | [TASK-00166](tasks/00166-TASK.md) | Record and retrieve durable asynchronous Task state | in-progress | — |
<!-- /planning:execution -->

This is the [Board](tasks/BOARD.md#whats-next) selection derived from TASK records, not a second execution priority
or proof of acceptance/delivery. Inspect the selected TASK's handoff for its next stage. Current human merge
handoffs live in the [Board delivery view](tasks/BOARD.md#current-delivery-handoffs), not the historical narrative below.

## Approved planning history

The [TASK Board](tasks/BOARD.md) owns current implementation status. Release preparation remains a separately
authorized operation, not an unfinished product EPIC. The decomposition narrative below records approved scope
and dependency edges at planning time, not current blockers or implementation status. Completed dependencies
remain history; the Board derives unfinished blockers from TASK records.

[EPIC-00009 — Execution introspection](epics/00009-EPIC.md),
[EPIC-00010 — Generic MCP Prompts](epics/00010-EPIC.md),
[EPIC-00011 — Generic MCP Subscriptions](epics/00011-EPIC.md) and
[EPIC-00012 — Generic MCP Tasks](epics/00012-EPIC.md) are approved. EPIC-00009's complete four-TICKET split is
accepted and written: [TICKET-00040](tickets/00040-TICKET.md) recording,
[TICKET-00041](tickets/00041-TICKET.md) safe summaries/activity,
[TICKET-00042](tickets/00042-TICKET.md) protected reading/removal and
[TICKET-00043](tickets/00043-TICKET.md) read-only MCP presentation. EPIC-00010's complete single-TICKET decomposition
is also approved and written as [TICKET-00044 — Discover and retrieve authorized MCP Prompts](tickets/00044-TICKET.md).
EPIC-00011's complete four-TICKET split is approved and written:
[TICKET-00045](tickets/00045-TICKET.md) retained recording/removal,
[TICKET-00046](tickets/00046-TICKET.md) polling/history recovery,
[TICKET-00047](tickets/00047-TICKET.md) MCP live changes/current subscribers and
[TICKET-00048](tickets/00048-TICKET.md) optional Mercure output. EPIC-00012's complete four-TICKET split is approved
and written: [TICKET-00049](tickets/00049-TICKET.md) durable operation handles/status/results,
[TICKET-00050](tickets/00050-TICKET.md) requested input,
[TICKET-00051](tickets/00051-TICKET.md) cooperative cancellation and
[TICKET-00052](tickets/00052-TICKET.md) optional live Task state/current-state recovery.
The [execution introspection map](wayfinder/mcp-execution-introspection-map.md#planning-handoff) is Closed with all
four EPIC handoffs and all thirteen requirement TICKETs accepted and written. TICKET-00040's complete three-TASK
split is now accepted and written:
[TASK-00147](tasks/00147-TASK.md) session/phase recording (no blockers),
[TASK-00148](tasks/00148-TASK.md) activity/usage recording (blocked by TASK-00147), and
[TASK-00149](tasks/00149-TASK.md) protected-detail recording (blocked by both). John approved DBAL qualification
on SQLite, MySQL and PostgreSQL and initially database-backed protected content, not a filesystem/object-storage
adapter. TICKET-00041's complete three-TASK split is now also accepted and written:
[TASK-00150](tasks/00150-TASK.md) authorized overview/execution pages (blocked by TASK-00148),
[TASK-00151](tasks/00151-TASK.md) execution summary/phases (blocked by TASK-00150), and
[TASK-00152](tasks/00152-TASK.md) activity/safe references (blocked by TASK-00150 and TASK-00149, not TASK-00151).
John approved direct DBAL reads on SQLite, MySQL and PostgreSQL with no asynchronous projection or Event Store
dependency. Safe availability evidence does not depend on TICKET-00042's public protected reads/removal.
TICKET-00042's complete two-TASK split is now also accepted and written:
[TASK-00153](tasks/00153-TASK.md) separately authorized protected-detail reading (blocked by TASK-00149/00150), and
[TASK-00154](tasks/00154-TASK.md) ordinary protected-detail removal with integrated evidence (blocked by TASK-00153/00152).
Both reuse database-backed detail on SQLite, MySQL and PostgreSQL; read/removal permissions remain separate.
No retention orchestrator, filesystem adapter or MCP removal Tool is added. No prior read TASK depends on removal.
TICKET-00043's complete two-TASK split is now also accepted and written:
[TASK-00155](tasks/00155-TASK.md) safe MCP inspection (blocked by TASK-00151/00152), and
[TASK-00156](tasks/00156-TASK.md) separately protected MCP inspection (blocked by TASK-00155/00153).
Both reuse shared Queries and guarded HTTP/Tool contracts; final encoded bounds include text duplication and
protocol overhead. Removed-content protocol fixtures require no TASK-00154 dependency or removal Tool.
EPIC-00009's complete TASK decomposition is accepted/written. This is a planning-completion statement only;
current implementation and unfinished blockers are derived from TASK records on the [Board](tasks/BOARD.md).
TICKET-00044's complete single-TASK decomposition is now also accepted and written as
[TASK-00157](tasks/00157-TASK.md), with no unfinished blockers. It owns the full Prompt list/get HTTP journey:
bounded explicit catalog, lazy selected providers, string arguments, authenticated stateless continuation,
private/zero-TTL defaults and bounded text/Resource-link results. EPIC-00010's planning decomposition is complete;
no introspection, Subscriptions or Tasks dependency is added.
TICKET-00045's complete two-TASK split is also accepted/written:
[TASK-00158](tasks/00158-TASK.md) durable safe-notice recording, with no unfinished blockers, then
[TASK-00159](tasks/00159-TASK.md) authorized removal/history-loss integrity, blocked only by TASK-00158.
Approved mechanics use dedicated DBAL storage on SQLite/MySQL/PostgreSQL, producer/scope-bound content comparison,
commit-ordered notice positions and atomic bounded access-safe history-loss updates. TICKET-00046 owns public
recovery Queries/Tool; there is no reverse dependency or mandatory Event Store/projection.
TICKET-00046's complete two-TASK split is also accepted/written:
[TASK-00160](tasks/00160-TASK.md) authorized summary/history/detail (blocked by TASK-00158), then
[TASK-00161](tasks/00161-TASK.md) shared recovery Query/MCP polling (blocked by TASK-00160/00159).
Direct DBAL reads on all three databases preserve live page/per-page inspection separately from bounded committed-
position recovery. Protected opaque continuation, coherent history-loss/access assessment and qualified completion
avoid false catch-up claims; final encoded bounds never advance past omitted eligible notices. Neither slice requires
unfinished introspection/Prompts or live MCP/Mercure.
TICKET-00047's complete three-TASK split is also accepted/written:
[TASK-00162](tasks/00162-TASK.md) live Tool/Resource subscriptions and technical tracking (blocked by TASK-00161),
[TASK-00163](tasks/00163-TASK.md) current-subscriber inspection (blocked by TASK-00162), and
[TASK-00164](tasks/00164-TASK.md) optional Prompt changes (blocked by TASK-00162/00157).
Cross-process shared Query sourcing and DBAL registrations with owner-bound freshness/closure support truthful live
behavior and inspection. Bounded waits/queues/frames, current emission permission and actual native streaming preserve
safe recovery without a broker/Mercure dependency. Prompt integration does not block the first two slices.
TICKET-00048's complete single-TASK decomposition is accepted/written as
[TASK-00165](tasks/00165-TASK.md), optional retained-notice Mercure publication, blocked only by TASK-00161.
It owns bounded safe mapping, current topic/public-private policy, actual post-commit publication/failure isolation
and shared recovery, reusing existing publishers without an MCP-live dependency. Controlled hub adapter proof and
real three-database recovery are distinct from deployed browser receipt. EPIC-00011's eight-TASK decomposition is complete.
TICKET-00049's complete three-TASK split is accepted/written: [TASK-00166](tasks/00166-TASK.md) shared durable lifecycle
(no unfinished blockers), [TASK-00167](tasks/00167-TASK.md) bounded removal (00166), and
[TASK-00168](tasks/00168-TASK.md) complete MCP activation/combined journeys (00166, 00169, 00170). The extension has no polling-only capability flag. TICKET-00050/00051 supply behavior/method handling
from 00166; 00168 owns final activation and combined Tool-start/input/cancel evidence, without a circular supplier
blocker. All 00168 supplier identities are now resolved; it is ready-for-agent with unfinished blockers. Removal is independent.
TICKET-00050's complete single [TASK-00169](tasks/00169-TASK.md) is accepted/written, blocked only by 00166. It owns retained
forms/confirmation, lifetime request identity, partial/mixed response admission, atomic duplicate protection and actual
tasks/update handling without partial extension advertisement. It owns input/producer-terminal races; TICKET-00051 owns
cancellation-intent/input races and 00168 owns combined Tool-start HTTP evidence. No reverse activation/live dependency.
TICKET-00051's complete single [TASK-00170](tasks/00170-TASK.md) is accepted/written, blocked by 00166 and 00169 for actual
input-intent integration. It owns durable bounded cancellation intent, cross-process authorized observation, harmless
repeats/terminal no-ops, actual tasks/cancel handling and three-database race/failure evidence. Intent is not actual
stopping or compensation; consumers decide stopping and report outcomes. Preserve accepted input/confirmation and terminal
winners. 00168 owns final activation/combined HTTP proof; no reverse activation/removal/live dependency is introduced.
No implementation or global execution priority is assigned; worktree choice remains explicit.
TICKET-00052's complete single [TASK-00171](tasks/00171-TASK.md) is accepted/written, blocked only by 00162 and 00168.
It owns live taskIds integration, complete currently authorized state/baseline through shared Queries, atomically bound
Task-scoped get/live revision comparison, bounded coalescing and current-state reconnect recovery. Full state stays out
of notices/Mercure/tracking; unsafe output stops honestly. Real three-database HTTP/race/failure evidence, native/API/schema
compatibility, docs/CONTEXT.md, exact coverage and full build remain implementation obligations.
All map planning is complete: four EPICs, thirteen TICKETs, 25 TASKs (00147–00171). No implementations are accepted by
this planning operation. Next: `/skill:next` to consult the authoritative Board; no task selection, execution authority,
global priority or worktree choice follows automatically. Ready Frontier remains the execution-selection source.
Map closure does not authorize implementation, consumer coordination changes or release; no release target is assigned.

[EPIC-00008](epics/00008-EPIC.md) has nine approved requirement TICKETs. John selected TICKET-00031 through TICKET-00039
and approved nineteen complete PR-sized TASKs (one each for TICKET-00031 through TICKET-00034, three for TICKET-00035,
five for TICKET-00036, three for TICKET-00037, one for TICKET-00038 and three for TICKET-00039):

- [TASK-00127 — Make Validation Reuse Exception-Safe](tasks/00127-TASK.md), a regression-first bug repair.
- [TASK-00128 — Add Strict Pagination Construction](tasks/00128-TASK.md), a code-first additive feature with separate
  legacy limitation evidence, not an immediate legacy-constructor repair.
- [TASK-00129 — Add Immutable JSON Snapshots](tasks/00129-TASK.md), a code-first opt-in feature with snapshot-aware
  text reconstruction and fresh mutable outputs, leaving legacy factories and Doctrine hydration unchanged.
- [TASK-00130 — Give StreamId Tuple Value Semantics](tasks/00130-TASK.md), a direct Identifier promotion with changed
  collection/JSON behavior. John's work-intake amendment permits minor delivery with explicit migration notes,
  superseding its original major-only boundary.
- [TASK-00131 — Correct Quoted Email Part Extraction](tasks/00131-TASK.md), a regression-first repair preserving
  email representation, case-sensitive identity, separate canonicalization and existing Doctrine conversions.
- [TASK-00132 — Add Canonical IP Address Values](tasks/00132-TASK.md), a code-first additive IP-family hierarchy
  with normalization, strict family parsing and retained IPv4-mapped IPv6 identity.
- [TASK-00133 — Add Lexical E.164 Phone Numbers](tasks/00133-TASK.md), a code-first additive lexical value with
  explicit optional adoption and preserved string-based SMS addressing.
- [TASK-00134 — Add Calendar and Local Time Values](tasks/00134-TASK.md), Date/Time and native ISO-backed WeekDay.
- [TASK-00135 — Add Exact Elapsed Durations](tasks/00135-TASK.md), independent signed integer-microsecond Duration
  with exact checked conversions/arithmetic.
- [TASK-00136 — Construct Strict Zoned DateTimes](tasks/00136-TASK.md), explicit-zone/DST/instant/native semantics,
  blocked by TASK-00134.
- [TASK-00137 — Add Inclusive Calendar Date Ranges](tasks/00137-TASK.md), inclusive/single-date intervals,
  blocked by TASK-00134.
- [TASK-00138 — Add Half-Open Instant Ranges](tasks/00138-TASK.md), zone-independent instant membership/identity,
  blocked by TASK-00136.
- [TASK-00139 — Add Exact Decimal Arithmetic](tasks/00139-TASK.md), bounded normalized exact arithmetic and native
  RoundingMode semantics with an extension-free baseline; no TASK blockers.
- [TASK-00140 — Define 49 Common Currency Values](tasks/00140-TASK.md), a bounded owned 49-code/0-2-3 scale
  set with no outside catalog; implementation done with independent review pending.
- [TASK-00141 — Calculate and Allocate Exact Money](tasks/00141-TASK.md), captured scale/safe readers, checked
  compatible-unit arithmetic and signed largest-remainder allocation; blocked by TASK-00139 and TASK-00140.
- [TASK-00142 — Express Transport-Neutral Application Failures](tasks/00142-TASK.md), all eleven extensible categories,
  diagnostic/cause preservation, neutral retry hints and unchanged structured validation; no TASK blockers.
- [TASK-00143 — Present Safe Errors Through PSR-15](tasks/00143-TASK.md), shared safe projection/diagnostics through
  a complete opt-in middleware path, with separate legacy compatibility; blocked by TASK-00142.
- [TASK-00144 — Integrate Safe Symfony Error Handling](tasks/00144-TASK.md), native exception lifecycle/negotiation,
  reporting ownership and deprecated-path migration; blocked by TASK-00143.
- [TASK-00145 — Integrate Safe Laravel Error Handling](tasks/00145-TASK.md), native rendering/reporting composition
  and legacy controller/provider compatibility; blocked by TASK-00143, independent of TASK-00144.

The first seven TASKs and TASK-00134/TASK-00135/TASK-00139/TASK-00140/TASK-00142 have no TASK dependencies;
temporal/Money slices retain their true dependencies above. John's 2026-10-09 bounded-set amendment replaces the
previous currency-data hold; Money still depends on the implemented Currency and Decimal capabilities. No execution priority is assigned. TASK-00130 uses the main checkout
and normal develop-based branch under its explicit owner-approved minor exception. All nine TICKETs are now
decomposed; consult the Board for execution selection and outstanding decisions. TICKET-00039's shared PSR-15 slice
consumes TASK-00142's vocabulary before the independent Symfony/Laravel integrations. New category existence and
planning approval do not contain current legacy disclosure. Planning has not started implementation, secured any
consumer, waived framework support evidence or approved an exact release.

The EPIC covers value objects, validation invariants, transport-neutral application failures and opt-in safe HTTP
error presentation. The primary target remains additive/deprecation-first next-minor delivery; TICKET-00034's direct
StreamId promotion has a scoped owner-approved minor exception, not a claim of backward compatibility. Incompatible enforcement
and replacement of legacy error-handling defaults also belong in a following major. TICKET-00039 consumes TICKET-00038's
application failure vocabulary; other areas have no new cross-TICKET prerequisite. Other potential 2.0 work begins
with fresh planning in the repository that owns the proposed scope; do not reopen archived work as an active commitment.

## Completed and retired work

Use the [EPIC archive](epics/archive/README.md), [TICKET archive](tickets/archive/README.md), and
[TASK archive](tasks/archive/README.md). Historical completion narratives remain in their records; current
status is generated from metadata.
