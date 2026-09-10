---
id: T-00087
prd: PRD-00018
title: Transfer the Canonical Pre-Submit Quality Gate to Every Starter
status: ready-for-agent
blocked_by: T-00041
---

# Transfer the Canonical Pre-Submit Quality Gate to Every Starter

## Outcome

Create one repository-local implementation ticket in each Symfony, Laravel, Yii, CodeIgniter, and Slim starter
for the lean, complete pre-submit gate required by ADR 0026 and PRD-00018. Fight Common retains the portfolio
contract; each starter owns its framework-specific tooling, implementation, verification, and delivery state.

## Scope

- In scope: five local ticket handoffs, Fight Common 1.2 and PHPCS-standard adoption, framework-specific
  extensions, test topology and pruning, exact unit-coverage policy, hosted-CI delegation, release-boundary
  separation, and evidence links back to this tracker.
- Out of scope: implementing or publishing any starter gate from Fight Common, centralizing the five builds,
  replacing meaningful framework-native checks, retaining release qualification in ordinary builds, or treating
  one starter as proof for another.

## Acceptance Criteria

- [ ] Symfony, Laravel, Yii, CodeIgniter, and Slim each own one local implementation ticket linked from this
      tracker and PRD-00018.
- [ ] Every local ticket makes `./bin/build` the single pre-submit test gate and runs every retained unit,
      integration, functional, frontend, and browser test exactly once.
- [ ] Every starter requires Fight Common `^1.2`, imports its installed `FightCommon` PHPCS standard with
      repository-owned paths and exclusions, and removes copied or divergent Fight coding rules.
- [ ] Every local gate runs ordinary Composer validation, syntax and formatting checks, PHPStan, Deptrac, Rector
      dry-run, and direct PHPUnit unit coverage with exact 100% owned production statements and no coverage-ignore
      directives or baselines that hide failures.
- [ ] Deptrac enforces the starter's Adapter -> Application -> Domain direction, fails on unclassified production
      code, and keeps framework types at the Adapter/composition boundary.
- [ ] Unit tests use `#[CoversClass]` and alone satisfy coverage. Necessary Integration and Functional journeys use
      `#[CoversNothing]`; tooling and structural tests do not become a catch-all for scaffold, planning, cache,
      configuration, wrappers, or ignore-policy assertions.
- [ ] Test fixtures and capability probes remain test-only. A starter does not publish global profile aggregates,
      synthetic Domain events, receipt authorities, or other production services solely to make tests convenient.
- [ ] Hosted CI delegates to the repository-owned `./bin/build` instead of maintaining a second ordered gate, and
      local success is never reported as hosted-CI success.
- [ ] Candidate-warning parsers, lowest/latest lanes, support receipts, auxiliary locks and digests, disposable
      candidate clones, packed-artifact checks, and production `--no-dev` inspection are absent from the ordinary
      build. Package release workflows qualify real candidates only when releasing.
- [ ] Every retained test protects the starter's owned production behavior or a valuable application journey; no
      starter remains a Fight Common certification harness or evidence generator.
- [ ] Each starter ticket is planned, approved, implemented, verified, committed, pushed, reviewed, and merged
      independently; this tracker records links and outcomes without claiming delivery on their behalf.

## Verification

- `./bin/planning-check` passes in Fight Common after all five repository-local ticket links are recorded.
- Each linked starter ticket names its focused checks, canonical `./bin/build`, exact coverage evidence, and hosted
  CI state separately.

## Completion Notes

Pending published Fight Common 1.2 verification through T-00041 and repository-local ticket creation.
