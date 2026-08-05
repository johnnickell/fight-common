# Define the plan, package, certify, and publish skill contracts

**Labels:** `wayfinder:grilling`, `wayfinder:prototype`
**Mode:** HITL
**Status:** Open
**Map:** [Fight Common Release Coordination](../fight-common-release-coordination-map.md)
**Depends on:** [Define supported release lines and the compatibility contract](WF-002-supported-lines-and-compatibility-contract.md), [Design deterministic release commands and evidence](WF-003-deterministic-release-commands-and-evidence.md)

## Question

What exact input, evidence, approval, stop, idempotency, and handoff contract belongs to each of
`fight-common-plan-release`, `fight-common-package-release`, `fight-common-certify-release`, and
`fight-common-publish-release`?

## Must decide

- permitted actor and read-only, local-mutation, or external-mutation classification;
- deterministic command allowlist and forbidden capabilities for each skill;
- how plans bind version, branch, candidate commit, prior tag, support policy, and evidence;
- handoffs and resumable states across planning, packaging, certification, and publication;
- approval granularity for branch creation, file mutation, merges, tags, pushes, GitHub Releases,
  and Packagist-affecting recovery;
- how a skill proves postconditions instead of treating command completion as success;
- catalog routing and canonical links from `CLAUDE.md` and `AGENTS.md` without duplication.

## Resolution boundary

Resolve interfaces and operator-visible behavior. Cheap prototypes are allowed; `.agents` skills,
commands, workflows, and external mutations are not.
