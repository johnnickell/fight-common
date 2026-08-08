# Define the release-operations runbook

**Labels:** `wayfinder:grilling`, `wayfinder:prototype`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
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

## Resolution

The operator guide uses one top-level dispatcher followed by journey-specific cards. The
dispatcher classifies work from the repository and release state first, then change intent,
affected supported lines, urgency metadata, and release class. It routes to the correct journey
without allowing an operator-selected workflow to contradict the target ref or release state.

The runbook defines these journeys: feature, unreleased fix, supported-line patch, forward port,
minor or major release, maintenance-line transition, and incomplete publication. Urgent or
security-sensitive work is metadata layered onto the ordinary journey; it adds visibility,
escalation, and timing information but never creates a hotfix path or bypasses review,
certification, evidence, or approval gates.

Every journey card has the same operator-facing contract:

1. when to use it and the routing decision that selects it;
2. required inputs and evidence;
3. approval gates and bounded effect authorizations;
4. phase-labeled links to the canonical `bin/release` commands and skills;
5. expected postconditions and evidence;
6. stop states, troubleshooting boundaries, and escalation ownership; and
7. cancellation, resume, and the next handoff action.

Each journey follows the same operator loop: inspect, classify, plan, review and approve, execute
one bounded effect, verify its postcondition, then hand off or stop. Stop states are first-class
outcomes. A stop records its evidence and exactly one resumable next action; cancellation preserves
the run and its evidence, and material input changes require a new plan and run.

The patch journey requires an explicit affected, unaffected, or unknown result for every supported
line before selecting the oldest affected line. An unknown result stops routing. The runbook shows
oldest-supported-line-first patching and ordered forward ports in compact Git-flow tables or
diagrams, while linking to WF-001 and WF-005 as the normative topology contracts.

Minor and major releases share one release journey. A comparison table explains differences in
release class, compatibility evidence, approvals, and branch topology without duplicating the
underlying contracts. Incomplete publication reconciles each possible external effect
independently, bound to the immutable plan, candidate, version, and evidence-manifest identity.

Troubleshooting is limited to diagnosis, evidence collection, canonical recovery-command
selection, and escalation ownership. Conflicts, failed certification, signing failures, and
incomplete GitHub or Packagist publication never receive improvised remediation or blind retries.

The runbook introduces the terms **operator journey**, **journey card**, **routing decision**,
**stop handoff**, and **effect authorization** in `CONTEXT.md`. This information architecture is
owned by this ticket; it does not require a separate ADR.

## Acceptance scenarios

- A feature targeting `develop` routes to the feature journey and cannot be routed to a released
  patch journey by operator preference alone.
- An unreleased defect follows the ordinary `develop` journey; urgency metadata changes escalation
  visibility but does not bypass review or certification.
- A released defect produces explicit affected, unaffected, or unknown evidence for every supported
  line; unknown evidence stops before base-branch selection.
- A supported-line patch selects the oldest affected supported line first and presents each newer
  forward port as a separate reviewed and certified step.
- A minor and a major release use the same release journey and expose their topology and evidence
  differences in a comparison table.
- A conflict, failed certification, signing failure, or incomplete publication displays the stop
  state, supporting evidence, escalation owner, and exactly one next action.
- Cancelling a run preserves its evidence; changing a bound input requires a new plan and run.
- Publication recovery verifies GitHub tag, GitHub Release, assets, Packagist metadata, and
  clean-install proof independently before advancing.
- A runbook command link routes to the canonical repository command or skill without restating its
  policy contract.
