---
id: T-00022
prd: PRD-00008
title: Introduce mandatory architecture enforcement
status: done
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

- [x] Deptrac verifies the canonical `Domain <- Application <- Adapter` dependency direction.
- [x] Domain cannot depend on Application or Adapter; Application cannot depend on Adapter; Adapter may depend
  on both inward layers.
- [x] Domain depends only on itself and PHP internals.
- [x] Application may depend on Domain, PHP internals, neutral PSR contracts, and the explicitly documented
  `CronExpression` utility exception; other concrete third-party implementations are forbidden.
- [x] `Scheduler` executes commands through the existing Application-owned `ProcessRunner` port rather than
  constructing Symfony Process directly.
- [x] Scheduler preserves its public due evaluation, locking, output, failure, and notification behavior.
- [x] Adapter may depend on Domain, Application, PHP internals, PSRs, and its explicitly configured
  infrastructure packages.
- [x] Standards may depend only on PHP internals, PHP_CodeSniffer, and Slevomat; runtime layers cannot depend on
  Standards.
- [x] Existing violations are resolved without a baseline, skipped violation, or unassigned Fight Common
  namespace.
- [x] Deptrac is a development dependency, is documented as optional for consumers, and becomes a mandatory
  shared-gate command.

## Parent

PRD-00008 — Architecture Enforcement.

## Outcome

Fight Common now enforces the complete runtime and Standards dependency graph with exact layer-specific
allowances, mandatory violation and unassigned-token checks, no baseline or skipped dependency, and focused
positive and hostile fixtures. Scheduler builds the Application-owned `Process` through `ProcessBuilder` and
executes it through a required `ProcessRunner`; the former Scheduler `processFactory` test seam was explicitly
removed with John’s approval after repository history showed that it duplicated the already-existing port.
This documented constructor break must be classified for a major release before publication.

## Verification

- Rector, PHPStan, PHPCS, strict Composer validation, planning validation, and `git diff --check` pass.
- Deptrac reports 0 violations, 0 skipped violations, 0 uncovered dependencies, 3,437 allowed dependencies,
  and no unassigned tokens.
- The complete disposable-database PHPUnit lifecycle passes 3,024 tests and 5,397 assertions.
- Clover coverage is exact at 8,826/8,826 statements and 1,847/1,847 methods.
- Independent Spec review passes 10/10 acceptance criteria; independent Standards review reports no blockers.
