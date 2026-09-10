# ADR 0026: Lean Pre-Submit and Release Qualification

- Status: accepted
- Date: 2026-09-10
- Supersedes: ADR 0006 and ADR 0008 where they require dependency-refresh or release-qualification work in the
  ordinary pre-submit gate
- Refines: ADR 0007 and ADR 0025

## Context

The Fight repositories accumulated tests of tests, shell wrappers, build ordering, configuration text, coverage
parsers, Composer behavior, CI workflows, candidate receipts, dependency lanes, and release infrastructure. These
checks made ordinary feature work slow and brittle without adding equivalent confidence in production behavior.

The portfolio needs one understandable development gate, exact unit-test coverage of owned PHP, a small number of
meaningful integration and journey tests, and a separate release boundary for expensive candidate qualification.
Fight Common 1.2 also publishes the canonical `FightCommon` PHP_CodeSniffer standard that active PHP repositories
will consume instead of maintaining divergent copies.

The `project-*` repositories are real starter applications and products in their own right. They are not Fight
Common fixtures, compatibility probes, release receipts, or evidence generators. Their tests protect the starter's
owned behavior and valuable user journeys; Fight Common qualifies its own releases at its own release boundary.

## Decision

### Core testing standard

- `./bin/build` runs every retained test. No ordinary unit, integration, functional, frontend, or browser test is
  opt-in or hidden outside the pre-submit gate.
- PHPUnit retains `requireCoverageMetadata="true"`.
- Owned production PHP requires exact 100% statement coverage from direct unit tests using `#[CoversClass]`.
- Integration and functional tests use `#[CoversNothing]`. They prove boundaries and journeys without filling
  unit-coverage gaps.
- Frontend tests are risk-based and have no percentage target.
- Browser tests are limited to critical cross-system journeys, but every retained browser test runs in
  `./bin/build`.
- A test that is too unimportant, brittle, or expensive for every pre-submit build is deleted rather than hidden
  behind another command.

### Pre-submit build contract

Every applicable repository's `./bin/build` runs each relevant stage once:

1. Dependency installation and ordinary manifest validation.
2. Syntax, formatting, static analysis, architecture checks, and Rector dry-run.
3. Direct unit tests with exact PHP statement coverage.
4. Necessary integration tests.
5. A small functional suite.
6. Frontend typechecking, linting, selected tests, and a production build when applicable.
7. Every retained critical browser journey.
8. Planning validation when the repository has committed planning records.

Configuration is validated directly by its owning tool. The build may run Symfony container or YAML linting,
Doctrine validation, TypeScript compilation, or equivalent real validators. PHPUnit does not test their
configuration text or assert the build's command ordering.

There is no third, hidden "complete" suite. Hosted CI delegates to `./bin/build`; hosted success and exact remote
SHA verification remain separate delivery evidence rather than additional test suites.

### Release boundary

Release-candidate qualification is a release activity, not a pre-submit test suite.

- Ordinary builds do not run lowest/latest dependency lanes, candidate-warning parsers, support receipts,
  disposable candidate clones, packed-artifact checks, production `--no-dev` inspection, or equivalent release
  ceremony.
- Consumer applications and starter repositories remove candidate-specific machinery rather than retaining it as
  opt-in local tests.
- A starter does not carry a test, fixture, receipt, or build lane whose only purpose is to prove Fight Common.
  Shared-package defects discovered through real starter behavior return to the package that owns them.
- A package release workflow validates the real candidate, archive, production dependency graph, supported
  dependency envelope, and a small set of clean consumers when that package is actually released.
- Release checks produce evidence for the exact candidate at release time; unrelated feature commits do not pay
  their cost.
- Release scripts and receipts are not unit-tested as product code. Running the real release operation proves the
  artifact end to end.
- Applications keep one ordinary `composer.lock`. Auxiliary lowest locks and lock digests are removed. Ephemeral
  release qualification may resolve other supported dependency combinations without committing extra lockfiles.

Fight Common retains the real `./bin/release certify <version>` seam established by ADR 0025. Its release-only
dependency lanes, archive, `--no-dev` consumer, package-surface validation, and evidence remain outside
`./bin/build`.

### Test retention

Keep:

- Direct unit tests of production behavior and failure cases.
- Integration tests crossing a real framework, database, messaging, filesystem, or installed-consumer boundary.
- A few valuable HTTP, console, or application journeys.
- Frontend tests for meaningful state transitions, validation, authorization, asynchronous behavior, and complex
  transformations.
- Browser tests for critical user journeys that cannot be proved adequately below the browser boundary.

Delete:

- Tests of shell wrappers, build scripts, CI YAML, Dockerfile text, PHPUnit configuration, command ordering,
  coverage parsers, hooks, documentation layout, and generated-file checks.
- Tests that only prove Composer, PHPUnit, PHPStan, Rector, Deptrac, Symfony, or another maintained tool behaves as
  documented.
- Render-only frontend tests, snapshot churn, CSS or class assertions, prop-plumbing tests, one-test-per-service
  wrapper patterns, and duplicated page tests.
- Broad provider or capability matrices for features the application does not use.
- Duplicate full-stack tests for branches already proved by direct unit tests.
- Fixtures whose only consumer is deleted certification machinery or a duplicated journey.
- Hollow tests written solely to cover dead or unused production code; delete that production code instead.

Thin wrappers remain untested. If a wrapper contains meaningful safety or business decisions, that logic moves
into owned PHP and receives direct unit coverage.

### FightCommon coding-standard adoption

Fight Common self-hosts and releases its canonical `FightCommon` ruleset in 1.2. After 1.2 is published, every
active PHP consumer:

- requires `johnnickell/fight-common:^1.2` and records the resolved version in its normal lockfile;
- keeps PHP_CodeSniffer and Slevomat as development tools;
- loads the installed Fight Common ruleset from `vendor/johnnickell/fight-common`;
- owns its scan paths and exclusions locally; and
- invokes PHPCS from `./bin/build`, removing copied or divergent Fight rules once replaced.

The ruleset is a versioned public development contract under ADR 0004. Importing it does not make consumer file
selection or release qualification Fight Common's responsibility.

## Rollout

1. Finish Fight Common documentation tickets T-00093 through T-00099 in their dependency order and obtain every
   separately required review and publication authorization.
2. Simplify Fight Common before 1.2: remove `tests/Tooling` and file/text meta-tests, keep exact direct unit
   coverage, retain meaningful public-consumer integration, and keep real certification release-only.
3. Run the simplified `./bin/build`, create a fresh exact-commit candidate, run
   `./bin/release certify 1.2.0`, and separately authorize tag, push, GitHub Release, and Packagist publication.
4. Use `project-symfony` as the reference consumer. Adopt `^1.2` and the installed PHPCS standard; remove its four
   tooling tests, auxiliary locks and digest, receipts, candidate validation, production-autoload checks, broad
   provider journeys, and unused fixtures; retain focused unit, kernel integration, middleware, homepage, and
   other valuable starter behavior. The result is a Symfony starter product, not the reference certification
   harness for Fight Common.
5. Apply the policy to Fight AccessControl, Fight CMS, Omphalos, Epic, and the generic project. Remove their
   tooling and wrapper tests, duplicated coverage passes, and low-value frontend tests. Converge Fight CMS and
   Omphalos to exact unit coverage by bounded context, deleting dead code before adding tests.
6. Apply the reference shape to `project-laravel`, `project-yii`, `project-slim`, and `project-codeigniter`.
   Remove candidate certification, receipts, lowest lanes, production-install inspections, framework example
   tests, unused fixtures, and unused capability journeys; add exact unit coverage for owned code and retain the
   minimum real framework integration and functional behavior. Each remains an independently useful starter with
   framework-native product ownership.

Each repository owns one bounded cleanup ticket linked to this ADR. No new epic or PRD exists solely to manage
test cleanup. Each repository is complete only after its canonical `./bin/build` passes.

Dialvault is archived dead code and is excluded from adoption and cleanup. Epic Content remains outside this
executable-code policy while it has no production code.

## Verification

For each active repository:

- Configure distinct unit, integration, and functional suites; only unit produces the coverage report.
- Prove unit `coveredstatements === statements` with no coverage-ignore directives.
- Run integration and functional suites once with `#[CoversNothing]`.
- Run every retained frontend and browser test once in `./bin/build`.
- Confirm no ordinary test exists outside the suites invoked by `./bin/build`.
- Confirm consumer builds contain no candidate receipt, auxiliary lock, lock digest, production-autoload
  inspection, or tooling-test reference.
- Record before/after test counts and build duration. Preserved behavioral confidence, not deletion count, is the
  acceptance criterion.
- Require the repository's canonical `./bin/build` to pass. Report hosted CI and exact remote-SHA verification as
  separate delivery evidence.

## Consequences

Feature work receives one comprehensive but intentional pre-submit result. Exact coverage remains strict without
letting integration tests conceal unit-test gaps. Real boundary tests remain visible, and release confidence comes
from qualifying the actual artifact at the moment that evidence matters.

The cleanup deliberately removes infrastructure theater and duplicated evidence. It does not weaken architecture,
static analysis, the published coding standard, production behavior, or release qualification.
