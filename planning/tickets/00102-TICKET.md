---
id: T-00102
prd: PRD-00006
title: Simplify the Fight Common Pre-Submit Gate Before 1.2
status: ready-for-agent
blocked_by: T-00099
---

# Simplify the Fight Common Pre-Submit Gate Before 1.2

## Outcome

Apply ADR 0026 to Fight Common itself before final 1.2 acceptance. Leave one complete `./bin/build` that protects
owned product behavior without testing its own wrappers, configuration, test framework, or release machinery, then
certify the resulting exact release candidate through the separate release operation.

## Scope

- In scope: test inventory and classification; deletion of tooling, wrapper, configuration-text, coverage-parser,
  hook, command-order, and duplicated tests; removal of orphaned fixtures; unit-only exact statement coverage;
  deletion of package-root `configuration/yii/`; removal of `YiiCapabilityConfiguration`; an ownership audit of
  `Adapter/ServiceContainer/Yii`; Fight Common dependency and documentation cleanup; a linked handoff assigning
  application composition to project-yii; direct tool validation; one complete pre-submit gate; documentation of
  before/after counts and duration; and a fresh exact-commit `./bin/release certify 1.2.0` result.
- Out of scope: weakening `requireCoverageMetadata`, letting integration tests contribute unit coverage, hiding
  retained tests behind opt-in commands, changing public runtime APIs, publishing 1.2, changing consumer
  repositories, or deleting meaningful PHPCS-standard product tests.

## Acceptance Criteria

- [ ] Every production class under the owned coverage scope has direct unit coverage through `#[CoversClass]`, and
      the unit suite alone proves exact statement equality without coverage-ignore directives.
- [ ] Necessary integration, documentation-contract, and functional tests use `#[CoversNothing]` and prove real
      public, installed-consumer, framework, persistence, filesystem, or application boundaries.
- [ ] `tests/Tooling` and equivalent tests of shell wrappers, builds, CI, Dockerfile text, PHPUnit configuration,
      hooks, coverage parsing, command ordering, and maintained third-party tools are removed.
- [ ] Tests and fixtures for the published `FightCommon` PHPCS ruleset remain where they prove its real public
      sniff behavior, diagnostics, properties, fixing, or compatibility contract.
- [ ] Fixtures with no retained behavioral consumer and dead production code kept alive only for coverage are
      removed.
- [ ] Package-root `configuration/yii/` is deleted, and Fight Common ships no project-owned Yii merge plan or
      application capability configuration files.
- [ ] `YiiCapabilityConfiguration` is removed. Application-selected collaborators, template paths, fallback
      choices, and dependency-injection composition are assigned to project-yii through a linked downstream ticket
      or explicit handoff rather than retained in this reusable package.
- [ ] The remaining `Adapter/ServiceContainer/Yii` providers are audited individually. Fight Common retains only
      reusable bindings from Fight contracts to package adapters; provider code that selects application policy or
      defaults is removed or assigned to project-yii.
- [ ] `yiisoft/config` is removed from Fight Common's development dependencies, allowed plugins, documentation, and
      tests if no retained package behavior requires it.
- [ ] The aggregate `CapabilityProviderIntegrationTest` and its manual loading of package-root Yii configuration
      are removed. Any retained reusable provider behavior is proven by focused direct unit tests.
- [ ] `./bin/build` invokes every retained ordinary test exactly once and runs Composer validation, syntax, PHPCS,
      PHPStan, Deptrac, Rector dry-run, unit coverage, necessary integration, and planning validation directly.
- [ ] Lowest/latest dependency lanes, archive construction, production `--no-dev` inspection, and release evidence
      are absent from `./bin/build` and remain solely in `./bin/release certify`.
- [ ] Before/after test counts and build duration are recorded, with retained behavioral confidence rather than
      deletion count as the acceptance criterion.
- [ ] The complete simplified `./bin/build` passes from the task worktree.
- [ ] A fresh clean exact commit passes `./bin/release certify 1.2.0`; its evidence supersedes the earlier candidate
      for the later T-00017 and T-00035 decisions without performing publication.

## Verification

- Run focused tests while classifying and simplifying each test area.
- Confirm `configuration/yii/`, `YiiCapabilityConfiguration`, and the aggregate Yii configuration-loader test no
  longer exist, and confirm no retained dependency, documentation, or test reference requires them.
- Confirm the project-yii ownership handoff is recorded without changing that consumer repository in this ticket.
- Run the unit coverage gate and require `coveredstatements === statements`.
- Run `./bin/planning-check` and `git diff --check`.
- Run the canonical `./bin/build` and require a completed exit status of `0`.
- After the implementation commit is clean and separately eligible for certification, run
  `./bin/release certify 1.2.0` and inspect its exact-commit evidence.

## Completion Notes

Pending completion of the Fight Common documentation presentation and first hosted Pages verification through
T-00099.
