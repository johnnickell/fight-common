# Roadmap

Live roadmap links point to current planning records. Terminal records may be archived only through the explicit
archive operation in `CONVENTIONS.md`; the operation preserves identifiers and repairs these links so historical
outcomes remain navigable.

## In progress

| Epic | Target | Status | Outcome |
| --- | --- | --- | --- |
| [EPIC-00001](epics/00001-EPIC.md) | 1.2.0 | in-progress | Event Sourcing and integration guidance are complete; documentation presentation, the ADR 0026 Fight Common gate simplification, fresh certification, and final release acceptance remain |
| [EPIC-00003](epics/00003-EPIC.md) | 1.2.0+ | in-progress | T-00056 proved thin certification; T-00102 will certify the documentation-complete lean-gate candidate before separately authorized publication and Packagist qualification |
| [EPIC-00004](epics/00004-EPIC.md) | 1.2.0+ | in-progress | Public API authority and framework support are complete; T-00087 will transfer the lean starter-product gate after public Fight Common 1.2 qualification |
| [EPIC-00005](epics/00005-EPIC.md) | 1.2.0+ | in-progress | The complete repaired documentation candidate is accepted through T-00098 with local artifact, editorial, responsive, accessibility, and canonical-build evidence; T-00099 remains the hosted verification boundary after separately authorized publication, and the GitHub-profile adaptation remains separately governed |

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
6. T-00098 accepted the complete predeployment documentation product; complete T-00099 after separately
   authorized publication to verify the first hosted artifact.
7. Apply ADR 0026 to Fight Common through T-00102, pass the complete lean pre-submit gate, and certify a fresh
   exact candidate without publishing it.
8. Complete T-00017 final release acceptance, then separately authorize T-00035 publication and T-00041 Packagist
   qualification. After the public 1.2 package is verified, T-00087 transfers the imported PHPCS standard and lean
   starter-product gate into the five independently owned starter repositories.

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
