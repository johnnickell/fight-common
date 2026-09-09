# Roadmap

Live roadmap links point to current planning records. Terminal records may be archived only through the explicit
archive operation in `CONVENTIONS.md`; the operation preserves identifiers and repairs these links so historical
outcomes remain navigable.

## In progress

| Epic | Target | Status | Outcome |
| --- | --- | --- | --- |
| [EPIC-00001](epics/00001-EPIC.md) | 1.2.0 | in-progress | Event Sourcing core, durable storage, aggregate repository, checkpointed projection, durable post-commit publication, and optional Symfony mapping-provider autoconfiguration complete; integration documentation and release acceptance remain |
| [EPIC-00003](epics/00003-EPIC.md) | 1.2.0+ | in-progress | T-00056 has implemented thin certification and awaits its exact committed-candidate run before separately authorized publication |
| [EPIC-00004](epics/00004-EPIC.md) | 1.2.0+ | in-progress | Public API authority, framework support, package isolation, and five starter receipts are complete; exact committed-candidate compatibility certification remains |
| [EPIC-00005](epics/00005-EPIC.md) | 1.2.0+ | in-progress | The exact documentation runtime, protected artifact-based Pages workflow, canonical Fight identity, browser-qualified Atlas Deck shell, validated repository entry surface, and executable framework-neutral Quick Start are complete through T-00092; T-00093 and T-00094 remain independently ready reader journeys; hosted deployment and the separately governed GitHub-profile adaptation remain later boundaries |

## Route to 1.2.0

1. WF-024 is closed with the complete framework-adapter matrix accepted in ADR 0024.
2. PRD-00014 and PRD-00015 are refreshed from WF-024 without duplicate specifications.
3. The reconciled implementation graph is published in T-00050 through T-00054, T-00058, T-00069 through
   T-00075, T-00077, and T-00084 through T-00086; T-00049, T-00050, T-00051, T-00053, T-00059, and T-00060 remain valid
   unchanged. T-00050 through T-00054, T-00057 through T-00060, T-00069 through T-00075, T-00077, and T-00084 through T-00086
   are complete.
4. Run `$aios /ask-matt` at each planning or implementation boundary. It returns the current human decision and
   first ready implementation ticket from the board rather than selecting by ticket number.
5. Run `$aios /coordinate-build T-xxxxx` for a ready ticket with several vertical slices, or
   `$aios /implement T-xxxxx` when the approved ticket is already one small tracer bullet. Complete one ticket at
   a time and return to `/ask-matt` after its verified handoff. Commit, push, pull-request, merge, and publication
   effects remain separately approved actions.
6. With the framework-adapter graph and five starter receipts complete, finish T-00056 exact-commit certification
   and then final release acceptance. GitHub publication and
   Packagist qualification remain separate human-authorized outcomes; the simulated maintenance and operator
   framework is closed `wontfix`.

Additive adapter support discovered after 1.2 may ship in 1.3. Incompatible namespace removal and contract cleanup
remain reserved for 2.0.

## Completed

| Epic | Target | Status | Outcome |
| --- | --- | --- | --- |
| [EPIC-00002](epics/00002-EPIC.md) | 1.2.0 | done | Reusable Fight Common coding standards, exact architecture and coverage enforcement, skip-free disposable database verification, one shared quality gate, reproducible local and latest-compatible hosted builds, and opt-in pre-commit enforcement are complete |

## Released

| Version | Outcome |
| --- | --- |
| 1.1.0 | Current stable Fight Common release |
