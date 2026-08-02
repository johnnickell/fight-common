---
id: T-00022
prd: PRD-00008
title: Introduce mandatory architecture enforcement
status: ready-for-agent
blocked_by:
---

# Introduce Mandatory Architecture Enforcement

## What to Build

Make Fight Common's dependency model executable from application behavior through the complete production
graph. Scheduler command jobs use the existing `ProcessRunner` port, and Deptrac enforces every runtime and
Standards allowance without an unclassified namespace or legacy exception.

## Blocked By

None — can start immediately.

## Acceptance Criteria

- [ ] Deptrac verifies the canonical `Domain <- Application <- Adapter` dependency direction.
- [ ] Domain cannot depend on Application or Adapter; Application cannot depend on Adapter; Adapter may depend
  on both inward layers.
- [ ] Domain depends only on itself and PHP internals.
- [ ] Application may depend on Domain, PHP internals, neutral PSR contracts, and the explicitly documented
  `CronExpression` utility exception; other concrete third-party implementations are forbidden.
- [ ] `Scheduler` executes commands through the existing Application-owned `ProcessRunner` port rather than
  constructing Symfony Process directly.
- [ ] Scheduler preserves its public due evaluation, locking, output, failure, and notification behavior.
- [ ] Adapter may depend on Domain, Application, PHP internals, PSRs, and its explicitly configured
  infrastructure packages.
- [ ] Standards may depend only on PHP internals, PHP_CodeSniffer, and Slevomat; runtime layers cannot depend on
  Standards.
- [ ] Existing violations are resolved without a baseline, skipped violation, or unassigned Fight Common
  namespace.
- [ ] Deptrac is a development dependency, is documented as optional for consumers, and becomes a mandatory
  shared-gate command.

## Parent

PRD-00008 — Architecture Enforcement.
