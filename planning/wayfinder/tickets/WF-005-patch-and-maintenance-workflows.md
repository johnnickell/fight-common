# Define patch and maintenance-line workflows

**Labels:** `wayfinder:grilling`, `wayfinder:prototype`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Open
**Map:** [Fight Common Release Coordination](../fight-common-release-coordination-map.md)
**Depends on:** [Define supported release lines and the compatibility contract](WF-002-supported-lines-and-compatibility-contract.md), [Design deterministic release commands and evidence](WF-003-deterministic-release-commands-and-evidence.md)

## Question

How do `fight-common-maintain-release-line` and `fight-common-patch-release-line` safely select,
create, patch, forward-port, support, and retire maintenance branches without a separate hotfix
flow?

## Must decide

- exact boundary between line lifecycle and applying an already-reviewed fix;
- accepted fix identity and proof that implementation review is complete;
- branch naming, oldest-affected-line selection, change classification, and patch eligibility;
- pull-request and certification evidence required on every affected line;
- ordered forward ports, provenance, conflict stops, and semantic-difference handling;
- current-line integration with `main` without allowing an older line to replace newer contents;
- maintenance-branch creation, protection, support transitions, EOL, and read-only preservation;
- security or urgent-fix handling as metadata without bypassing the ordinary safety contract.

## Resolution boundary

Resolve workflows and state transitions. Do not apply a real fix, create maintenance or patch
branches, change branch protection, or publish a patch release.
