# Synthesize the release implementation epic, PRD, and tickets

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common Release Coordination](../fight-common-release-coordination-map.md)
**Depends on:** [Define supported release lines and the compatibility contract](WF-002-supported-lines-and-compatibility-contract.md), [Design deterministic release commands and evidence](WF-003-deterministic-release-commands-and-evidence.md), [Define the plan, package, certify, and publish skill contracts](WF-004-release-skill-contracts.md), [Define patch and maintenance-line workflows](WF-005-patch-and-maintenance-workflows.md), [Define GitHub and Packagist publication and provenance](WF-006-publication-and-provenance.md), [Define the release-operations runbook](WF-007-release-operations-runbook.md)

## Question

How should the resolved release decisions become one canonical Fight Common epic, coherent PRDs,
ordered executable tickets, and verification slices for later implementation?

## Must decide

- implementation seams across commands, tests, CI, policy, `.agents`, catalog routing, and runbook;
- vertical slices that prove one operator journey at a time;
- dependencies and execution order without one oversized release-automation ticket;
- migration of accepted Wayfinder decisions into canonical planning without duplication or drift;
- complete acceptance gate and safe test doubles for GitHub, Git, signing, and Packagist boundaries;
- implementation branch strategy and publication-independent completion criteria.

## Resolution boundary

Create the implementation-ready planning handoff after every dependency is closed. Do not implement
the resulting tickets, publish a release, or silently mark implementation work ready without the
normal Fight Common planning gates.

## Resolution

The handoff is one epic with four PRDs and eight vertical implementation tickets:

- EPIC-00003 — Deterministic Release Coordination;
- PRD-00010 — Deterministic Release Foundation;
- PRD-00011 — Release Lifecycle and Publication Recovery;
- PRD-00012 — Maintenance-Line and Patch Workflows; and
- PRD-00013 — Operator Surfaces and Release Integration.

The first proving slice is a normal feature targeting `develop`, exercised through plan, package, and
certify. Git, signing, authorization, GitHub, and Packagist are explicit ports with deterministic fakes,
effect ledgers, contract tests, and crash points. Real external checks remain separately authorized
operator work.

Each implementation ticket gets its own branch from `develop`, uses the composed offline verification
gate, and does not require real publication for completion. The implementation order is foundation,
normal release packaging, certification, publication and recovery, maintenance, patching and forward
ports, then operator skills/catalog and runbook/CI integration.

Accepted decisions are synthesized once into their epic, PRD, or ticket home and linked to the closed
Wayfinder record. The original eight-ticket handoff was refined after the full PRDs were approved: the
oversized foundation, publication, patch, and integration slices became twelve tickets, T-00032 through
T-00043. Their exact blockers, acceptance criteria, and verification commands are canonical in the ticket
files.

The complete handoff gate includes planning validation; Rector, PHPStan, PHPCS, PHPUnit, and exact
coverage; locked/lowest/latest dependency lanes; public API and compatibility evidence; deterministic
archive and clean-install proof; Git/ref and signing verification; offline approval, failure, stop,
resume, and partial-publication tests; and epic-to-ticket traceability.
