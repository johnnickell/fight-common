# Planning Conventions

This document defines the canonical planning structure for Fight project repositories.
Use these conventions consistently across all projects.

## Directory Structure

```
planning/
  adr/              Architecture Decision Records
  agents/           Domain-specific agent instructions
  epics/            High-level work destinations
  specs/            Product Requirements Documents (PRDs)
  tickets/          Executable work items
  wayfinder/        Pre-implementation investigation maps and decision tickets
  ROADMAP.md        Strategic progress record
  README.md         Directory guide
```

## File Naming

| Artifact | Prefix | Example |
|----------|--------|---------|
| Epic | `NNNNN-EPIC.md` | `00001-EPIC.md` |
| PRD | `NNNNN-PRD.md` | `00003-PRD.md` |
| Ticket | `NNNNN-TICKET.md` | `00034-TICKET.md` |
| ADR | `NNNN-short-description.md` | `0005-layer-dependency-matrix.md` |
| Wayfinder Map | `descriptive-name-map.md` | `fight-common-release-coordination-map.md` |
| Wayfinder Ticket | `WF-NNN-short-description.md` | `WF-001-release-destination-and-boundaries.md` |
| Wayfinder Research | `WF-NNN-description-research.md` | `WF-014-contract-audit-research.md` |

Identifiers are independent five-digit sequences. Ticket identifiers are displayed as `T-NNNNN`.

Every planning directory keeps its copy-ready template beside its live artifacts. Templates begin with `_`, are
not planning records, and are never assigned an identifier. Copy a template before authoring a new artifact; do
not alter a template to record a live decision.

## Ticket Frontmatter

Every ticket file begins with YAML frontmatter:

```yaml
---
id: T-00034
prd: PRD-00011
title: Brief description of the work
status: ready-for-agent
blocked_by: T-00033
---
```

## Ticket Status Lifecycle

| Status | Meaning |
|--------|---------|
| `needs-triage` | Not yet classified |
| `needs-info` | Blocked on a decision or missing evidence |
| `ready-for-agent` | Decision-complete and executable when dependencies are done |
| `ready-for-human` | Requires human judgment or an external action |
| `in-progress` | Actively being changed |
| `done` | Acceptance criteria and verification are complete |
| `wontfix` | Intentionally closed without implementation |

Do not store `blocked` as a status; derive it from unfinished `blocked_by` edges.

## BOARD.md

`planning/tickets/BOARD.md` is the canonical execution frontier. It must be structured as:

- **"What's Next?" Contract** — defines what `/ask-matt` or an unqualified "What's next?" returns
- **Now** — the current human decision requiring judgment
- **Ready Frontier** — rank-ordered tickets with no unfinished blockers
- **Waiting** — `ready-for-agent` tickets with unfinished `blocked_by` edges
- **Needs Info** — tickets waiting on decision authority
- **Recently Closed / Done** — terminal tickets with outcomes

## ROADMAP.md

`planning/ROADMAP.md` records strategic progress with three sections:

- **In progress** — a table of active epics with target version, status, and current outcome
- **Route to `<version>`** — numbered narrative steps describing the path to the next release
- **Completed / Released** — terminal epics and released versions

## Wayfinder Convention

Wayfinder maps are planning-only investigation documents for efforts whose implementation route
is not clear enough for an epic or PRD yet. Each map:

- Has a `Label: wayfinder:map` header
- Defines a clear destination
- Links to decision tickets (WF-NNN) that produce design decisions
- Has a `Frontier` section showing the next takeable ticket
- Produces an implementation handoff (epic, PRDs, and T- tickets) when complete

Wayfinder tickets (WF-NNN) document design decisions. Research files capture investigation output.
Wayfinder files are never executable implementation tickets.

### Wayfinder Maps

`planning/wayfinder/README.md` is the index of every map and its current charting state. It is the first
place to look when resuming planning after time away. A map is an index, not a second store of decisions:
each material decision belongs in one linked Wayfinder ticket, while the map gives a short linked summary.

Use `_MAP_TEMPLATE.md` for every new map. A map must contain, in this order:

1. a label and explicit `Active` or `Closed` status;
2. the destination and precise done condition;
3. scoped notes and decision summaries linked to their WF tickets;
4. a linked ticket table with type, mode, status, and dependencies;
5. blocking relationships when dependencies are non-trivial;
6. a single explicit `Frontier`, followed by fog and out-of-scope boundaries.

The Board may name one **Wayfinder Review** candidate only when an active map has a concrete, unblocked
frontier ticket suitable for `/grill-with-docs`. It must link to the map and its frontier ticket. If no such
map exists, the Board says so rather than inventing planning work and `/ask-matt` offers `/wayfinder` to chart
a new feature. This advisory pointer never overrides the Board's implementation frontier or the map's own
decision authority.

### Archive Operation — Explicit Command Only

Archiving is a deliberate repository-maintenance operation, never a completion side effect. Run it only when
explicitly asked to archive tickets, specs, epics, a named Wayfinder map, or a named set of those artifacts.
Use `./bin/archive-planning` with a dry run first and `--apply` only after its proposed moves are correct.
The command moves records and rewrites local Markdown links throughout live planning and archive directories;
never move files by hand.

| Request | Command shape | Destination |
|---------|---------------|-------------|
| archive tickets | `./bin/archive-planning tickets T-00001 … [--apply]` | `planning/tickets/archive/` |
| archive specs | `./bin/archive-planning specs PRD-00001 … [--apply]` | `planning/specs/archive/` |
| archive epics | `./bin/archive-planning epics EPIC-00001 … [--apply]` | `planning/epics/archive/` |
| archive a Wayfinder map | `./bin/archive-planning wayfinder map-name [--apply]` | map, tickets, and research under `planning/wayfinder/**/archive/` |

The operation fails closed unless every requested artifact is eligible:

- a ticket is terminal (`done` or `wontfix`);
- a PRD is terminal and all of its tickets are terminal;
- an epic is terminal and all of its PRDs are terminal; and
- a Wayfinder map is `Closed`, all of its linked decision tickets are `Closed`, its frontier is empty, and its
  resolution links to the resulting epic, PRD, or implementation ticket handoff. A map that still needs
  decisions or still needs its implementation handoff must remain live.

After an applied archive, run `./bin/planning-check`, inspect the rewritten links, refresh the relevant README
index and Board/ROADMAP projections, and commit the move plus reference repair together. Archives remain
addressable planning records; never renumber, flatten, or replace them with prose summaries.

## Epic Convention

Each epic file:

- Has YAML frontmatter with `id`, `title`, `status`, and `target` version
- Lists constituent PRDs
- Has a `Progress` section summarizing completed work

## PRD Convention

PRDs describe coherent product requirements. A PRD README tracks all PRDs with their status.

## Branch and Workflow Convention

- Branches follow `feature/<description>` from `develop`
- Never commit directly to `develop` or `main`
- Coordinate-build scratch lives in gitignored `.runs/`, never in `planning/`

## Pre-PR Synchronization Checklist

Before creating a pull request for any feature or bug fix:

1. **Update ticket status** — mark the working ticket `done` with verified acceptance criteria
2. **Update BOARD.md** — move the ticket from Ready Frontier/Waiting to Recently Done; recalculate the frontier if dependencies shifted
3. **Update parent PRD** — if the PRD README tracks completion status, verify it reflects the ticket's new state
4. **Update epic progress** — if the epic's Progress section needs to reflect the new milestone
5. **Update ROADMAP.md** — if the completed work moves a strategic milestone forward
6. **Run `./bin/planning-check`** (when available) to verify planning file consistency
7. **Verify `blocked_by` edges** — ensure no downstream tickets list the completed ticket as an unresolved blocker
8. **Refresh Wayfinder continuity** — update the Wayfinder index and the Board's Wayfinder Review pointer when
   a map frontier or handoff changed

The BOARD.md is canonical for execution order. After every ticket completion, recalculate
the "What's Next?" contract at the top of the board.
