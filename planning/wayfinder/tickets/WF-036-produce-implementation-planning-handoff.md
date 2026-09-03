# Produce the implementation planning handoff

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Open
**Map:** [Fight Common Documentation Presentation](../fight-common-documentation-presentation-map.md)
**Depends on:** [Select the documentation delivery architecture](WF-033-select-documentation-delivery-architecture.md), [Design the GitHub-profile adaptation](WF-034-design-github-profile-adaptation.md), [Define compatibility and presentation quality gates](WF-035-define-compatibility-and-quality-gates.md)

## Question

How should the closed presentation decisions become an implementation epic, coherent PRDs, and executable
tickets across Fight Common and the separately governed GitHub profile repository?

## Must decide

- the implementation epic destination and cross-repository authority boundaries;
- PRD slices for identity assets, content and information architecture, site presentation and delivery,
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
