# Planning Conventions

## Lightweight work routing

For “What's next?”, `next`, or an unqualified `/ask-matt`, use the routing entry in root AGENTS.md.
This read-only path precedes general orientation; full implementation, review, QA and delivery requirements
still apply when the selected workflow is invoked.

1. Run the documented read-only planning validator once when available. Failure stops selection with the
   actual error and planning-owner handoff; do not refresh views. Without a validator, disclose that limitation
   and check selected record metadata against the authored Board; do not claim generated freshness.
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

During authorized planning updates, keep current Board/Roadmap handoffs source-linked and concise: target,
phase, pending decision/owner. Keep Wayfinder inventory/frontier pointers current. These are navigation aids,
not another backlog or proof of delivery. Preserve generated sections and all historical decisions.

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

| Status | Meaning |
|---|---|
| `needs-triage` | Scope or ownership is unclassified |
| `needs-info` | A decision or required evidence is missing |
| `ready-for-agent` | Decision-complete and executable when dependencies permit |
| `ready-for-human` | Human judgment or an external action is next |
| `in-progress` | Implementation or revision is underway |
| `done` | Implementation acceptance and required local verification are complete for a TASK; parent completion follows terminal child statuses |
| `wontfix` | Intentionally closed without implementation |

Blocking is derived, not a stored status. Mark TASK implementation `done` before PR publication once implementation
acceptance and required local verification are complete. A green build alone does not establish acceptance.
Independent review may still be pending: state that explicitly in completion notes/handoffs and record its outcome,
publication, merge, release and deployment separately. Done grants none of those authorities. Preserve truthful
historical records rather than inventing review approval or rewriting prior outcomes.

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

`ROADMAP.md` retains strategy and milestone narrative; its EPIC status table and separate Planning frontier are
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
is read-only and fails on pending parent completion, stale views, invalid identifiers/parents, missing links, and
dependency cycles. Validation errors prevent writes to parent records and generated views. `./bin/build` already runs
the read-only check and must not rewrite planning as a side effect. Verify documentation and tooling directly;
do not add tests of Markdown, wrappers, configuration text, or planning tooling to the product suite.

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
`.runs/archive/` hold local execution material. [Review](../docs/engineering/standards/Review.md) owns the canonical
base-worktree report, independence, criteria and accept/revise contract. Durable requirements and outcomes belong in planning records.

Before a commit or PR, record verified acceptance and outstanding review honestly, update the PR link if known,
refresh views, and satisfy the canonical `./bin/build` gate (or the verified documentation-only reuse rule in
[Testing](../docs/engineering/standards/Testing.md#reusing-a-full-gate-result-after-documentation-only-follow-ups)). Use focused checks during iteration. Surface warnings,
notices, and deprecations. Release certification, deployment, and runtime enrollment remain separate operations.
