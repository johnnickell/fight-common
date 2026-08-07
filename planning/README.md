# Planning

This directory is the committed source of truth for Fight Common planning.

- `ROADMAP.md` records strategic progress.
- `epics/` describes destinations.
- `specs/` describes coherent product requirements.
- `tickets/` contains executable work; each ticket is canonical for its own status and dependencies.
- `tickets/BOARD.md` ranks the current execution frontier.
- `adr/` records architectural decisions.
- `agents/` contains focused working instructions.
- `wayfinder/` contains planning-only investigation maps and decision tickets for efforts whose
  implementation route is not clear enough for an epic or PRD yet.

Identifiers are independent five-digit sequences. Ticket identifiers are displayed as `T-NNNNN`. Valid statuses are `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `in-progress`, `done`, and `wontfix`. Blocking is derived from unfinished `blocked_by` edges and is not stored as a status.

Run `./bin/planning-check` after changing planning files. Coordinate-build scratch belongs in gitignored `.runs/`, never here.
