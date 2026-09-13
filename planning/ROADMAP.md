# Roadmap

Live roadmap links point to current planning records. Terminal records may be archived only through the explicit
archive operation in `CONVENTIONS.md`; the operation preserves identifiers and repairs these links so historical
outcomes remain navigable.

## In progress

| Epic | Target | Status | Outcome |
| --- | --- | --- | --- |
| [EPIC-00003](epics/00003-EPIC.md) | 1.2.0+ | in-progress | T-00017 accepted `d262866` certification and tree-identical `ef64fcb`; T-00035 validates `release/1.2.0`, then freshly certifies exact `main` before separately authorized publication and T-00041 Packagist qualification |
| [EPIC-00004](epics/00004-EPIC.md) | 1.2.0+ | in-progress | Public API authority and framework support are complete; T-00087 will transfer the lean starter-product gate after public Fight Common 1.2 qualification |

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
6. T-00098 accepted the complete predeployment documentation product, and T-00099 verified the exact protected
   Pages deployment and real public product after separately authorized publication.
7. T-00017 accepted the ten-outcome `d262866` certification and tree-identical `ef64fcb` merge under ADR 0027.
8. T-00035 validates the `release/1.2.0` candidate and separately authorizes its pull request to `main`; it then
   freshly certifies exact `main`, signs, and publishes the verified draft immutably. T-00041 then verifies the
   Packagist qualification. After the public 1.2 package is verified, T-00087 transfers the imported PHPCS standard and lean
   starter-product gate into the five independently owned starter repositories.

Additive adapter support discovered after 1.2 may ship in 1.3. Incompatible namespace removal and contract cleanup
remain reserved for 2.0.

## Completed

| Epic | Target | Status | Outcome |
| --- | --- | --- | --- |
| [EPIC-00001](epics/00001-EPIC.md) | 1.2.0 | done | Additive Event Sourcing, portable and Symfony composition, integration guidance, documentation presentation, exact product coverage, and final 1.2 compatibility acceptance are complete; exact-main certification and publication remain the separate ADR 0027 release boundary |
| [EPIC-00005](epics/00005-EPIC.md) | 1.2.0+ | done | Fight identity, the repository entry surface, capability-led guidance, Atlas Deck presentation, protected artifact-based Pages delivery, hosted HTTPS verification, and the first published route and anchor compatibility commitments are complete; profile adaptation remains a separately governed non-blocking follow-up |
| [EPIC-00002](epics/00002-EPIC.md) | 1.2.0 | done | Reusable Fight Common coding standards, exact architecture and coverage enforcement, skip-free disposable database verification, one shared quality gate, reproducible local and latest-compatible hosted builds, and opt-in pre-commit enforcement are complete |

## Released

| Version | Outcome |
| --- | --- |
| 1.1.0 | Current stable Fight Common release |
