# Planning

This directory is the committed source of truth for Fight Common planning.

- [Conventions](CONVENTIONS.md): hierarchy, templates, lifecycle, generated views, and archive operations.
- [TASK Board](tasks/BOARD.md): current work, priority, blockers, and PR links.
- [Roadmap](ROADMAP.md): strategy and current EPICs.
- [EPICs](epics/README.md): business destinations.
- [TICKETs](tickets/README.md): related use cases and requirements.
- [TASKs](tasks/README.md): bounded executable work, normally one PR each.
- [Wayfinder maps](wayfinder/README.md): uncertain destinations and decision frontiers.
- [Migration map](MIGRATION.md): legacy PRD and executable-ticket IDs and paths.
- `adr/`: architectural decisions; `agents/`: focused working instructions.

Each level has a separate five-digit sequence. Records own their metadata; the Board and indexes derive it.
Use `./bin/planning-check --write` to refresh marked sections, then `./bin/planning-check` to verify consistency.
The canonical `./bin/build` includes the read-only check. Copy-ready templates begin with `_` and are not records.

Archive only when explicitly requested, using `./bin/archive-planning` with a reviewed dry run before `--apply`.
Run material belongs in ignored `.runs/`; durable requirements and outcomes belong here.
