# Planning Conventions

## Lightweight work routing

For “What's next?”, `next`, or an unqualified `/ask-matt`, use the routing entry in root AGENTS.md.
This read-only path precedes general orientation; full implementation, review, QA and delivery requirements
still apply when the selected workflow is invoked.

1. Run `./bin/planning-check --check-delivery` once before selection or recommending a current publication/merge
   handoff. It checks local consistency and live state for explicitly marked current delivery handoffs only.
   OPEN retains the human merge handoff. A verified MERGED, accepted (`done`) and locally integrated handoff
   is resolved for this observation: continue selection without asking John for routine cleanup, even if its
   recorded marker remains. Report that observed result rather than repeating the Board's publication snapshot.
   Failure or unavailable evidence stops selection with the actual identity and missing evidence/integration or
   decision owner; do not refresh views or infer a merge. An ordinary offline `./bin/planning-check`
   pass is not delivery freshness. Without a validator, disclose that limitation and check selected metadata
   against the authored Board; do not claim generated or hosted freshness.
2. Read only the Board's current human decision, active work, and (if no active work) first ready entry.
   Use local section names and authoritative order. Return all active TASKs, not a new winner. Inspect selected
   TASK metadata and its current handoff, plus blocker metadata only as needed. An unresolved contradiction
   is a named input gap, not permission to audit every record or infer acceptance from `done`.
3. Read the Wayfinder index's map-status/frontier inventory. Closed or decision-complete maps need no historical
   reread. For active/open maps, read only current frontier and decision/dependency status or explicit external
   prerequisite evidence needed for availability. Unknown state or an absent frontier needs its owner's input.
   Multiple available maps remain independent choices; no global priority is implied.
4. Use the Board/Roadmap's current planning handoff for unfinished decomposition, including closed maps.
   Verify the named phase in the owning record's current progress/approval section. Map closure, parent status
   and child counts do not prove complete decomposition. If the handoff is missing or contradictory, report
   the missing phase/owner; do not reconstruct it by reading every closed map and descendant.
5. Read canonical review/QA identity and disposition only when they change the selected route. Unresolved
   identity is an evidence handoff, never acceptance. Confirm a suggested skill from supplied capability
   metadata/bindings; load its full instructions and references when invoked, not merely to recommend it.

Prefer heading searches and bounded section reads. Aim to retrieve no more than about 24,000 characters
of routing evidence; stop with a precise unresolved input before exceeding that scope unless the user requests
a deeper audit. Do not read append-only task histories, other worktrees' entire tasks, general architecture,
or all receiving skills to produce a recommendation. Return TASK, WAYFINDER, and relevant PLANNING/HUMAN INPUT;
stop without invoking a workflow or changing files. Missing indexes are reported, not fabricated.

During authorized planning updates, keep current planning/decomposition handoffs source-linked and concise:
target, phase, pending decision/owner. Current TASK selection belongs only in the generated Board `next` and
Roadmap `execution` blocks; do not duplicate mutable TASK status, blockers or merge claims in authored routing
prose. TASK records own stage/evidence details and the generated Board delivery block owns current merge handoffs.
Keep Wayfinder inventory/frontier pointers current. These are navigation aids, not another backlog or proof of
delivery. Preserve historical decisions in their owning records and label historical strategy/dependency prose.

Routing sources: [TASK Board](tasks/BOARD.md), [Wayfinder inventory](wayfinder/README.md), [Roadmap](ROADMAP.md).

Individual Markdown records own requirements, status, dependencies, and execution priority. Boards, indexes,
and Roadmap status/frontier tables are generated views. Authored strategy and decision narratives remain editable.

## Hierarchy and paths

| Level | Responsibility | Path and displayed ID |
|---|---|---|
| EPIC | Destination, business outcome, and boundaries | `planning/epics/00001-EPIC.md` · `EPIC-00001` |
| TICKET | Related use cases and requirements | `planning/tickets/00001-TICKET.md` · `TICKET-00001` |
| TASK | Bounded implementation, normally one PR | `planning/tasks/00001-TASK.md` · `TASK-00001` |
| SUBTASK | Dependency-ordered implementation assignment | Ignored `.runs/` material belonging to a TASK |

Each level has its own five-digit sequence. Preserve numbers and gaps; never reuse archived identities. Inspect
both live and archived records before allocating the next ID. The [migration map](MIGRATION.md) connects old PRD
and executable-ticket references to current records, including historical receipts. WF decision IDs are unchanged.

Every artifact directory keeps a copy-ready `_…_TEMPLATE.md`. Templates are not records and receive no ID. ADRs
remain in `adr/` with `NNNN-description.md` names; focused agent instructions remain in `agents/`.

Grilling writes the EPIC only. Decompose it into TICKETs, then TASKs before implementation. Record use cases,
commands, queries, events, expected side effects, validation, permissions, and observable acceptance evidence.
Explain concerns that are not applicable rather than silently omitting them.

A TASK normally delivers a complete use case. Layer-based SUBTASKs coordinate Domain, migrations, Application,
adapters, and UI according to dependencies. A substantial SUBTASK may have a separately planned PR; the parent
TASK owns complete acceptance and records the PR dependencies. Small bugs and chores may be standalone TASKs
with `kind: bug` or `kind: chore` and an empty `ticket` field. Do not reopen an archived parent for unrelated work.

## Metadata and lifecycle

```yaml
---
id: TASK-00103
ticket:
kind: chore
title: Adopt the EPIC, TICKET, and TASK planning surface
status: in-progress
order: 1
blocked_by:
pr:
---
```

TICKETs require an `epic: EPIC-NNNNN` parent. TASKs use `ticket: TICKET-NNNNN`, except standalone bugs/chores.
`blocked_by` holds comma-separated TASK IDs. Preserve edges after completion; unfinished blockers are derived.
`order` is an optional positive priority number, lower first. Unranked rows come last, with IDs breaking ties
only for deterministic display. `pr` is an optional full PR URL, not an assertion about live GitHub merge state.

### Current delivery handoffs

On a live TASK, Land sets optional `delivery: awaiting-merge` and the verified `pr` URL at the OPEN human
review/merge handoff. This is a **publication snapshot**, not a perpetual assertion that the PR is still open.
Neither `done`, a PR link nor historical completion prose creates a current delivery claim. The Board's generated
`planning:delivery` block mirrors recorded markers as "Published; verify live state"; only a fresh live check
establishes the current action. Keep historical acceptance/publication facts, not competing current claims.

`./bin/planning-check --check-delivery` is read-only. It checks local consistency and uses existing `gh` operator
access for at most 16 marked PRs, one 15-second request per PR. URL, number, repository, exact TASK/title,
TASK feature branch, develop base and full head OID must agree. OPEN retains the human handoff. MERGED resolves
it **without blocking selection or requiring a cleanup request** only when merge time/OID are valid, the TASK
is already `done`, the exact published head is an ancestor of that merge and that merge is an ancestor of local
HEAD. Local Git inspection is bounded, disables replacement objects/lazy fetching and never fetches or merges.
Report merge and integration as distinct verified facts, not as inferred acceptance or QA. A resolved snapshot
permits normal next-work routing even before a later authorized write retires the marker.

At authorized Work, Land or substantive planning intake, run `./bin/planning-check --reconcile-delivery` on the
selected feature branch before other record edits. This project-owned administrative operation is included in
those workflow scopes; do not ask John for another routine cleanup authorization. It performs the same live
verification, retires only verified merged/integrated `done` markers, appends exact merge/head/time/local-integration
evidence to each owning TASK, and regenerates views/eligible parents. OPEN markers remain unchanged. Repeated
use is idempotent; retired PRs are no longer queried, so normal work does not accumulate stale markers or require
historical scans. Review/QA and read-only routing never invoke this writing mode. Plain `--write` stays offline.

CLOSED WITHOUT MERGE requires a real disposition decision, not automatic completion. A merged PR with a
non-terminal TASK or missing local ancestry requires scope/integration recovery through authorized work. Missing
CLI/authentication, API/network failure, malformed/contradictory metadata and identity mismatch remain incomplete
verification. All such failures stop routing/reconciliation with the actual missing fact; they never infer merge,
clear markers, or rewrite even stale views. Validation/live-evidence errors prevent all planned writes. Unexpected
write/I/O failures require inspecting actual disk state before retry; this is not a filesystem transaction.

Land also requires live checks immediately before publication and after final metadata changes under the
[workflow binding](agents/shared-workflows.md#delivery-pre-submit-and-final-handoff), not hosted CI success.
All three modes are mutually exclusive. The checker never scans unmarked historical PRs, changes TASK acceptance,
checks CI, publishes, approves, merges or archives. Ordinary checks and both planning steps in `./bin/build`
remain offline/read-only; dependency/image preparation retains the build's existing behavior.

| Status | Meaning |
|---|---|
| `needs-triage` | Scope or ownership is unclassified |
| `needs-info` | A decision or required evidence is missing |
| `ready-for-agent` | Decision-complete and executable when dependencies permit |
| `ready-for-human` | Human judgment or an external action is next |
| `in-progress` | Implementation, revision or required independent Review/QA is underway |
| `done` | TASK implementation acceptance, required verification, independent Review acceptance and required QA are satisfied; parent completion follows terminal child statuses |
| `wontfix` | Intentionally closed without implementation |

Blocking is derived, not a stored status. Keep an implemented TASK `in-progress` while independent Review or
required QA is pending; name the current stage in its handoff. Use `ready-for-human` only for an actual human
judgment/external action. Mark `done` only after independent acceptance and satisfied QA, including justified
overall N/A, plus every required acceptance check. A green build alone is insufficient. Authorized Land normally
records this administrative closeout; Review/QA publish reports without changing planning. Record publication,
merge, release and deployment separately; done grants none of those authorities.

This prospective rule was approved in [TASK-00172](tasks/00172-TASK.md). Preserve historical implementation-complete
statuses and reports under their original policy; do not mass-reopen or infer current acceptance from old `done`.
The [Delivery contract](../docs/engineering/standards/Delivery.md#acceptance-and-administrative-closeout) separates
accepted content from administrative metadata and any required final-head delivery checks.

## Automatic parent completion

When a TASK becomes `done` or `wontfix`, run `./bin/planning-check --write` in the same completion
operation. It closes eligible TICKETs first, then EPICs, and refreshes the generated views. Count both live
and archived children. A live, non-terminal parent with at least one child closes when every child is terminal:
use `wontfix` when every child is `wontfix`, otherwise `done`.

Parents without children or with any unfinished child stay open. Preserve already-terminal and archived
parents. Child acceptance and intentional `wontfix` decisions remain with the child records; parent completion
requires no separate assessment, independent review, QA, confirmation, or skill invocation. Parent status does
not assert review, merge, release, or deployment, and completion never archives records automatically.

## Board and generated views

`tasks/BOARD.md` shows Active Work, Ready Frontier, Waiting, Needs Info, Human Action, Needs Triage, and Recently
Closed. Every section shows Order, TASK ID, Title, Parent TICKET, Status, Blocked by, and PR. Parent cells show ID
and title. Artifact and blocker IDs are visible links. Archives have separate indexes.

For "What's next?" or `/ask-matt`, return the current human decision/question and active TASK; otherwise return
the first executable TASK in Ready Frontier. Do not start a second TASK merely because its ID is lower. If there
is no executable work, say so. Execution priority is authored in record metadata rather than generated rows.

The Board's `next` block and Roadmap's `execution` block share the Board's record-derived classification and
ordering. Both surface Human Action and Needs Info, then all Active Work or the first Ready Frontier row. If neither
exists they state no TASK is executable and show the first Needs Triage and Waiting rows when present. They do not
select a workflow stage, infer independent acceptance or query PR state. The complete Board remains authoritative.

`ROADMAP.md` retains historical strategy and milestone narrative; its EPIC status table and separate Planning frontier are
generated. The frontier shows non-terminal EPICs without TICKETs and TICKETs without TASKs for decomposition,
counting live and archived children. [Automatic parent completion](#automatic-parent-completion) removes eligible
parents from unfinished planning work. Do not put decomposition in executable TASK Board rows. Parent
readiness and unresolved decisions still govern decomposition. Standalone chores appear on the Board without
inventing an EPIC. Live EPICs and TICKETs have generated child tables. Archived
progress prose remains historical completion evidence, not a live status source.

Generated sections use `<!-- planning:NAME -->` and `<!-- /planning:NAME -->`. After editing source records:

```bash
./bin/planning-check --write
./bin/planning-check
```

The first command validates records and links, closes eligible parents, then refreshes marked sections. The second
is read-only and fails on pending parent completion, stale views (including current TASK summaries), invalid
identifiers/parents, missing links and dependency cycles.
Validation errors prevent writes to parent records and generated views. `./bin/build` runs the read-only check
before expensive preparation and again after product/docs verification; neither check rewrites planning or queries
hosted PRs. A failure requires an explicit owner edit/regeneration followed by a fresh read-only check. Verify
documentation and tooling directly; do not add tests of Markdown, wrappers, configuration text, or planning
tooling to the product suite.

## Wayfinder

Maps describe uncertain planning destinations. Decision tickets remain `WF-NNN-description.md` under
`wayfinder/tickets/`; they are distinct from requirements TICKETs and implementation TASKs. Research remains
under `wayfinder/research/`.

Use `_MAP_TEMPLATE.md`: Active/Closed status, destination and done condition, notes, linked decision summaries,
decision table, blocking relationships, one Frontier, remaining fog, and exclusions. Decisions use
`_WAYFINDER_TICKET_TEMPLATE.md` with Map, Labels, Mode, Status, and Depends on links.

The generated map table includes every decision owned by that map, including closed decisions, with visible
WF IDs, title, type, mode, current status, and dependency IDs. Data comes from decision records. Authored Frontier
and decision summaries retain their meaning. A Closed map has no decision frontier and links to its implementation
handoff. Wayfinder indexes derive state from maps. Do not invent a frontier when all maps are closed.

## Map-wide planning order

For a selected Wayfinder map, finish its decisions and approved EPIC destinations first, then all accepted
TICKET decompositions, then all TASK decompositions, before recommending Board-based implementation selection.
Honor explicit human choices for a narrower slice or different order; do not invent dependencies or global
priority between maps. A Wayfinder decision may produce zero, one or several EPIC destinations; decision closure
or a grill-mode label does not itself invoke the EPIC-producing Grill skill. Grill creates one approved EPIC
per invocation, then returns to the map's remaining destinations.

Track decisions resolved, EPICs written/approved, TICKET decomposition accepted and TASK decomposition accepted
in the existing map/handoff and owning records. A child table or parent status does not prove complete planning.
Reuse live/archived records, preserve historical approvals and report uncertain scope instead of allocating
duplicates. A Closed map may still have a pending linked planning handoff without reopening decisions.

At useful stopping points give an explicit `Next:` command for the actual target and phase under the
[shared handoff rules](agents/shared-workflows.md#handoffs), or name the precise pending human decision.
Recommendations never invoke a workflow. The TASK Board still owns execution order and active work.

## Archive operation

Archive only on an explicit request, never as a completion side effect. Use the owning tool rather than moving
records by hand; inspect its dry run before applying it:

| Operation | Command shape | Destination |
|---|---|---|
| TASKs | `./bin/archive-planning tasks TASK-00001 … [--apply]` | `tasks/archive/` |
| TICKETs | `./bin/archive-planning tickets TICKET-00001 … [--apply]` | `tickets/archive/` |
| EPICs | `./bin/archive-planning epics EPIC-00001 … [--apply]` | `epics/archive/` |
| Wayfinder map | `./bin/archive-planning wayfinder map-name [--apply]` | Existing Wayfinder archive directories |

A selected TASK must be terminal. TICKETs additionally require all children terminal; EPICs require all TICKETs
terminal. A Wayfinder map must be Closed with all decisions Closed, no frontier, and a linked implementation
handoff. Archive moves preserve records, repair local Markdown references, and refresh generated views.

## Branches and completion

New TASK branches use `feature/task-NNNNN-<slug>` from `develop`, and PR titles use
`TASK-NNNNN — <TASK title>`. Preserve established branch/PR identities without retrospective renaming; release and
patch conventions remain unchanged. Never commit directly to `develop` or `main`. Choose the main checkout or an
isolated worktree with the user. Ignored `.runs/worktrees/`, `.runs/notes/`, `.runs/handoffs/`, `.runs/reviews/`, and
`.runs/archive/` hold local execution material. Use the prospective [evidence layout](agents/shared-workflows.md#ignored-evidence)
for Build coordination, task runs and canonical QA reports; do not migrate historical evidence implicitly.
[Review](../docs/engineering/standards/Review.md) and [QA](../docs/engineering/standards/QA.md) own their independent
base-worktree reports. Durable requirements and outcomes belong in planning records.

Before a commit or PR, record verified acceptance and outstanding review honestly, update the PR link if known,
refresh views, and satisfy the canonical `./bin/build` gate (or the verified documentation-only reuse rule in
[Testing](../docs/engineering/standards/Testing.md#reusing-a-full-gate-result-after-documentation-only-follow-ups)). Use focused checks during iteration. Surface warnings,
notices, and deprecations. Release certification, deployment, and runtime enrollment remain separate operations.
