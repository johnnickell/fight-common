---
id: T-00087
prd: PRD-00018
title: Transfer the Canonical Pre-Submit Quality Gate to Every Starter
status: ready-for-agent
blocked_by: T-00075
---

# Transfer the Canonical Pre-Submit Quality Gate to Every Starter

## Outcome

Create one repository-local implementation ticket in each Symfony, Laravel, Yii, CodeIgniter, and Slim starter
for the permanent clean-clone pre-submit gate required by PRD-00018. Fight Common retains the portfolio contract;
each starter owns its framework-specific tooling, implementation, verification, and delivery state.

## Scope

- In scope: five local ticket handoffs, a common minimum gate contract, framework-specific extensions, test
  topology, exact coverage policy, hosted-CI delegation, and evidence links back to this tracker.
- Out of scope: implementing or publishing any starter gate from Fight Common, centralizing the five builds,
  replacing framework-native checks, weakening an existing repository gate, or treating one starter as proof for
  another.

## Acceptance Criteria

- [ ] Symfony, Laravel, Yii, CodeIgniter, and Slim each own one local implementation ticket linked from this
      tracker and PRD-00018.
- [ ] Every local ticket makes `./bin/build` the single noninteractive clean-clone completion gate and retains all
      existing planning, dependency, production-install, framework-boot, database, frontend, documentation, and
      security checks that apply to that starter.
- [ ] Every local gate runs ordinary Composer validation with the exact temporary candidate-warning allowlist,
      the repository coding-standard/fixer check, PHPStan, Deptrac, Rector dry-run, PHPUnit, and exact 100%
      production statement coverage without coverage-ignore directives or baselines that hide new failures.
- [ ] Deptrac enforces the starter's Adapter -> Application -> Domain direction, fails on unclassified production
      code, and keeps framework types at the Adapter/composition boundary.
- [ ] Unit tests mirror owned `Adapter`, `Application`, and `Domain` source; Integration and Functional journeys
      remain separate; structural architecture checks do not become a catch-all for scaffold, planning, cache, or
      ignore-policy assertions.
- [ ] Test fixtures and capability probes remain test-only. A starter does not publish global profile aggregates,
      synthetic Domain events, receipt authorities, or other production services solely to make tests convenient.
- [ ] Hosted CI delegates to the repository-owned `./bin/build` instead of maintaining a second ordered gate, and
      local success is never reported as hosted-CI success.
- [ ] Each starter ticket is planned, approved, implemented, verified, committed, pushed, reviewed, and merged
      independently; this tracker records links and outcomes without claiming delivery on their behalf.

## Verification

- `./bin/planning-check` passes in Fight Common after all five repository-local ticket links are recorded.
- Each linked starter ticket names its focused checks, canonical `./bin/build`, exact coverage evidence, and hosted
  CI state separately.

## Completion Notes

Pending T-00075 and repository-local ticket creation.
