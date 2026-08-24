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

The BOARD.md is canonical for execution order. After every ticket completion, recalculate
the "What's Next?" contract at the top of the board.