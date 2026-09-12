# Contributing

Use this guide when changing Fight Common itself. Consumer installation, component adoption, and framework
composition belong in their respective guides; this route covers repository workflow, verification, and release
evidence for maintainers.

## Choose the change boundary

Start with the smallest owned boundary that can deliver the requested behavior:

- **Domain** contains framework-free business rules and value objects.
- **Application** coordinates use cases and may depend on Domain, PHP internals, PSR contracts, and the
  allowlisted scheduler expression contract.
- **Adapter** integrates frameworks and infrastructure through Application or Domain contracts.
- **Standards** publishes the orthogonal coding standard and has no runtime dependants.

The enforced dependency direction is `Adapter -> Application -> Domain`. Keep public behavior backwards
compatible: a deprecated public API remains supported for at least one released minor and is removed only in the
next major.

Before editing, read the relevant ticket, its parent PRD, and any accepted ADR named by the ticket. The live
[Board](https://github.com/johnnickell/fight-common/blob/develop/planning/tickets/BOARD.md) is the execution
frontier; `planning/CONVENTIONS.md` defines status, ordering, and completion updates.

## Create an isolated branch

Feature work starts from `develop`, never from `main`; do not commit directly to either protected branch.

```bash
git switch develop
git pull --ff-only
git switch -c feature/short-description
```

For coordinated or concurrent work, use a linked checkout under the repository's ignored run area:

```bash
git worktree add -b feature/short-description \
  .runs/worktrees/short-description develop
```

Run every command from the selected checkout. Keep investigation notes in `.runs/notes/`, reusable local handoffs
in `.runs/handoffs/`, and retired scratch in `.runs/archive/`. Those paths are local evidence and must not be
staged. Removing a worktree or other run material is a separate cleanup action.

## Implement and verify

Production tests cover owned production code and meaningful behavior. Every PHPUnit class extends
`Fight\Test\Common\TestCase\UnitTestCase` and carries explicit coverage metadata. Direct unit tests use
`#[CoversClass]`; `#[CoversNothing]` is reserved for qualifying integration or product-journey tests and cannot
hide missing direct coverage.

Documentation, generated files, wrappers, build orchestration, configuration text, and tooling are checked with
their owning commands and human inspection. Do not add tests that merely inspect those surfaces.

Use a non-interactive container command for focused feedback:

```bash
docker container run --rm -v "$PWD:/app:delegated" -w /app fight-common \
  php vendor/bin/phpunit tests/Domain/Specification/AndSpecificationTest.php
```

Useful interactive wrappers include:

```bash
./bin/phpunit --filter test_method_name
./bin/phpstan
./bin/deptrac
./bin/rector process src/ --dry-run
./bin/docs validate
```

The canonical pre-submit gate is:

```bash
./bin/build
```

It installs ordinary dependencies, validates the documentation artifact, provisions disposable MySQL and PostgreSQL
services, and runs Composer validation, syntax checks, PHPCS, PHPStan, Deptrac, Rector's dry run, direct unit tests
with exact statement coverage, integration tests, functional tests, and planning integrity. Dependency installation
uses the local `composer.lock` when one exists; because the lockfile is intentionally ignored, an unprepared
checkout resolves compatible dependencies and creates a local lockfile. A focused or fast run is feedback, not
completion evidence.

Hosted CI runs the same `./bin/build` command in the runner's Docker environment. Its result is separate hosted
evidence for the checked-out SHA, not a second dependency or quality lane.

To enable the tracked pre-commit gate:

```bash
git config core.hooksPath .githooks
```

The hook runs `./bin/build`. If it fails or is interrupted, diagnose the cause and run it again to completion.
Never use `git commit --no-verify`, disable, or otherwise bypass the hook merely because the gate is slow,
interrupted, or inconvenient. An exception requires explicit authorization for that exact commit and leaves
delivery unverified.

## Prepare the pull request

Before the final commit or pull request:

1. Verify every acceptance criterion with current evidence.
2. Mark the ticket `done` and record its verified outcome.
3. Move it to **Recently Done** on the Board and recalculate **What's Next?**.
4. Refresh parent PRD, epic, roadmap, and downstream `blocked_by` state when the completed outcome changes them.
5. Run `./bin/planning-check`, inspect the complete diff, and rerun `./bin/build`.

Open the feature pull request against `develop`. The hosted Tests workflow runs the complete pre-submit gate; the
documentation workflow builds and validates the generated site. A queued, skipped, cancelled, warning-bearing, or
no-step job is not passing evidence.

Commit, push, pull-request creation, merge, deployment, and cleanup are distinct effects. Perform only the effects
that have been explicitly authorized.

## Certify a release candidate

A successful `./bin/build` proves the checkout's submit gate; it does not certify or publish a release.
Certification additionally requires a reviewed local `composer.lock`, even though that file is ignored. Prepare
and verify that ordinary locked lane for the exact clean, committed candidate:

```bash
./bin/build
```

With that precondition satisfied, run:

```bash
./bin/release certify <version>
```

Certification binds its evidence to the exact `HEAD`, resolves latest-compatible and lowest-compatible lockfiles in
exported candidate workspaces, then exercises all three dependency lanes, builds the Composer archive, probes an
installed consumer, and writes the result under
`.runs/handoffs/`. See the
[release module guide](https://github.com/johnnickell/fight-common/blob/develop/release/README.md) for the full
contract.

Certification does not merge, tag, push, create a GitHub release, publish to Packagist, or deploy documentation.
Promotion from `develop` to `main`, tagging, publication, rollback, and maintenance-branch work each require their
own accepted plan and authorization. Do not infer a hotfix or backport route from urgency alone.

## Documentation workflow

Use the pinned documentation runtime:

```bash
./bin/docs preview
./bin/docs build
./bin/docs validate
```

`preview` serves the local site at `127.0.0.1:8000`. `build` creates the disposable, ignored `site/` artifact.
`validate` performs the strict render and current artifact checks. Review changed routes in the supported viewport
and color-scheme combinations before treating the documentation as accepted.

Pull requests validate documentation but do not deploy it. The hosted workflow deploys only after a push to
`main`; a local artifact, successful build, or uploaded workflow artifact is not production publication evidence.

## Related maintenance routes

- Adopt or configure the package's optional [Coding Standard](../coding-standard/index.md).
- Review the consumer-facing [Architecture](../../architecture/index.md) before changing layer ownership.
- Check [Framework Support](../../frameworks/framework-support/index.md) before changing adapter or provider claims.
- Use the [Quick Start](../../quick-start/index.md) as the representative portable consumer journey.
