# Wayfinder Maps

Wayfinder maps chart an uncertain feature before it becomes an epic, PRD, or implementation ticket. A map is an
index of linked decision tickets, not a second source of decisions. Start with an active map's **Frontier**; when
none is available, `/ask-matt` should offer `/wayfinder` to chart a new feature.

| Map | Status | Frontier | Handoff |
|---|---|---|---|
| [Fight Common Release Coordination](fight-common-release-coordination-map.md) | Closed | None | [EPIC-00003](../epics/00003-EPIC.md); [PRD-00010](../specs/00010-PRD.md) through [PRD-00013](../specs/00013-PRD.md); [T-00032](../tickets/00032-TICKET.md) through [T-00043](../tickets/00043-TICKET.md) |
| [Fight Framework Portability and Starter Projects](fight-framework-portability-map.md) | Closed | None | [EPIC-00004](../epics/00004-EPIC.md); [PRD-00014](../specs/00014-PRD.md) through [PRD-00018](../specs/00018-PRD.md); [T-00047](../tickets/00047-TICKET.md) through [T-00076](../tickets/00076-TICKET.md) |
| [Fight Common Documentation Presentation](fight-common-documentation-presentation-map.md) | Active | [Establish the Fight visual system and logo brief](tickets/WF-029-establish-visual-system-and-logo-brief.md); [Design the GitHub-profile adaptation](tickets/WF-034-design-github-profile-adaptation.md); [Define compatibility and presentation quality gates](tickets/WF-035-define-compatibility-and-quality-gates.md) | Pending |

Use `_MAP_TEMPLATE.md` and `tickets/_WAYFINDER_TICKET_TEMPLATE.md` for new work. `research/` holds linked
evidence, never a parallel decision record. Archive only through `../bin/archive-planning` after a map is Closed,
its decisions are Closed, its frontier is empty, and its implementation handoff is linked.
