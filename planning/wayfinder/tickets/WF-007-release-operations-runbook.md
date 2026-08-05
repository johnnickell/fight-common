# Define the release-operations runbook

**Labels:** `wayfinder:grilling`, `wayfinder:prototype`
**Mode:** HITL
**Status:** Open
**Map:** [Fight Common Release Coordination](../fight-common-release-coordination-map.md)
**Depends on:** [Define the plan, package, certify, and publish skill contracts](WF-004-release-skill-contracts.md), [Define patch and maintenance-line workflows](WF-005-patch-and-maintenance-workflows.md), [Define GitHub and Packagist publication and provenance](WF-006-publication-and-provenance.md)

## Question

What operator guide lets John select the correct workflow for a feature, unreleased defect,
supported-line patch, urgent security fix, forward port, minor or major release, maintenance-line
transition, or incomplete publication without memorizing Git choreography?

## Must decide

- decision tree from change type and affected versions to the correct base branch and skill;
- feature, unreleased-fix, patch-line, urgency, forward-port, release, and EOL journeys;
- required plans, approvals, evidence, stop states, cancellation, resume, and escalation;
- how the guide links to deterministic commands and skills without duplicating their rules;
- examples that explain git-flow plus oldest-supported-line patching without introducing hotfixes;
- troubleshooting boundaries for conflicts, failed certification, signing failures, and incomplete
  GitHub or Packagist publication.

## Resolution boundary

Design the guide's information architecture and acceptance scenarios. Do not write the final
implementation runbook or execute a release workflow.
