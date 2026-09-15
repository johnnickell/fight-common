# Produce the implementation planning handoff

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common Documentation Presentation](../../archive/maps/fight-common-documentation-presentation-map.md)
**Depends on:** [Select the documentation delivery architecture](WF-033-select-documentation-delivery-architecture.md), [Design the GitHub-profile adaptation](WF-034-design-github-profile-adaptation.md), [Define compatibility and presentation quality gates](WF-035-define-compatibility-and-quality-gates.md)

## Question

How should the closed presentation decisions become an implementation epic, coherent TICKETs, and executable
tickets across Fight Common and the separately governed GitHub profile repository?

## Must decide

- the implementation epic destination and cross-repository authority boundaries;
- TICKET slices for identity assets, content and information architecture, site presentation and delivery,
  repository README, profile adaptation, and verification where those are independently coherent;
- dependency-ordered executable tickets with acceptance criteria and repository-native gates;
- which work can proceed in parallel and which assets or decisions are shared prerequisites;
- explicit PHP public-API exclusion and stable-URL migration safeguards;
- separate commit, push, pull-request, deployment, profile-publication, and cleanup checkpoints; and
- the map-closing resolution links and next `/ask-matt` implementation frontier.

## Resolution boundary

Run `/to-spec` and `/to-tickets` only after the other linked decisions are closed. This ticket creates the
implementation-ready planning handoff and closes the map; it does not execute, publish, deploy, or clean up any
slice.

## Resolution

Create [Fight Common Documentation Presentation](../../../epics/archive/00005-EPIC.md) as the implementation destination
and close the Wayfinder map. Fight Common owns the canonical Fight identity assets, documentation, repository
README, and Pages delivery. The separately governed GitHub profile remains a later follow-up after the final
assets and live documentation URLs exist; create no Fight Common TICKET or ticket for it, and do not make it a
completion blocker.

Stage the permanent planning handoff instead of manufacturing all artifacts in this session. The epic records
the closed Wayfinder decisions and the expected requirement seams. The later explicit `/to-spec` session created
and obtained approval for [TICKET-00020](../../../tickets/archive/00020-TICKET.md), [TICKET-00021](../../../tickets/archive/00021-TICKET.md), and
[TICKET-00022](../../../tickets/archive/00022-TICKET.md). `/to-tickets` creates dependency-ordered implementation TASKs only after
those specifications exist. Verification belongs within each coherent TICKET rather than in a standalone testing
TICKET.

The approved `/to-tickets` handoff publishes [TASK-00088](../../../tasks/archive/00088-TASK.md) through
[TASK-00099](../../../tasks/archive/00099-TASK.md). TASK-00088 and TASK-00089 can start independently; their results join at the
Atlas Deck shell before the README and reader journeys proceed. The final candidate acceptance and hosted Pages
verification remain separate tickets because the production merge and deployment are separately authorized
effects.

The expected implementation slices cover canonical identity assets, the pinned documentation build and
artifact-based Pages workflow, grouped routes and navigation, the Atlas Deck shell, the repository README,
Quick Start and Architecture, grouped component guidance, integrated predeployment acceptance, and deployment
verification. Keep ticket and pull-request changes independently reviewable, but combine tightly coupled work
when splitting it would leave a nonfunctional intermediate state.

Implementation flows through feature work into `develop`. After the documentation work is complete, John will
separately authorize and perform the `develop` to `main` merge that permits Pages deployment. The grouped routes
remain mutable until that first production publication and become compatibility commitments afterward. PHP
public API changes remain excluded. Implementation, commit, push, pull request, merge, deployment, profile
publication, and cleanup remain separate approvals.

The Board now exposes TASK-00088 and TASK-00089 as the first documentation implementation frontier without changing
the in-progress framework-receipt work. TASK-00090 through TASK-00099 remain waiting on their explicit blocking edges.
