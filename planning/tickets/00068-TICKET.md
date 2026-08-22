---
id: T-00068
prd: PRD-00019
title: Move release coordination into a maintainer-only module
status: done
blocked_by: T-00040
---

# Move Release Coordination into a Maintainer-Only Module

## What to Build

Relocate the existing release coordination implementation, supporting scripts, tests, and fixtures into one
top-level maintainer module loaded exclusively through Composer development autoloading. Preserve `bin/release` as
the sole command and preserve every existing release result, effect, artifact, crash, and resume behavior. Move the
MkDocs not-found override beneath the documentation tree and keep it out of ordinary published content.

This is one wide behavioral-preserving refactor: the namespace and ownership move touches every release call site
at once, so it lands atomically with all quality-gate and journey-path updates rather than through temporary public
aliases.

## Delivery Contract

- Branch: `feature/release-tooling-isolation`, created from current `develop` in a disposable linked worktree.
- Runtime: repository-owned PHP 8.5 Docker environment; focused commands are non-interactive and the final submit
  evidence comes from `./bin/build`.
- Primary seams: the public `bin/release` process journey, Composer development versus no-development autoloading,
  the complete repository quality gate, and the rendered MkDocs site.
- Vertical slices: first establish the maintainer module and namespace while preserving command behavior; then
  move release tests and fixtures with their quality coverage; then relocate and verify the documentation override;
  finally prove consumer isolation and the complete gate.
- Exclusions: no new release behavior or commands, no internal interface redesign, no archive exclusion policy,
  no publication or credential effects, and no T-00033 packaging implementation.

## Acceptance Criteria

- [x] All release production declarations live in the top-level release module under `Fight\Release\`; none remain
      production-autoloaded beneath `Fight\Common`, and no compatibility alias exposes the old names.
- [x] Composer development autoloading resolves the release namespace for repository maintainers, while a clean
      `--no-dev` consumer installation registers no release namespace and cannot autoload a representative release
      declaration.
- [x] `bin/release` remains the only stable release executable and preserves the existing canonical runtime,
      environment isolation, authenticated machine result, governed exit, configured crash, and cleanup behavior.
- [x] Release PHP scripts, Python helpers, unit tests, conformance tests, journey tests, runtime tests, and fixtures
      are owned by the release module and no stale path or old namespace reference remains.
- [x] Existing inspect, plan, prepare, artifact-store, run-state, crash, credential-isolation, and boundary journeys
      pass without changing their externally observable contracts.
- [x] PHPUnit and exact coverage include both Fight Common runtime source and release source, and every release
      production class retains complete statement coverage with required coverage metadata.
- [x] PHP syntax, Python syntax, PHPStan, PHPCS, Rector, and Deptrac all inspect the release module; architecture
      enforcement rejects runtime dependencies on release tooling and reports no uncovered release declaration.
- [x] Quality-gate contract fixtures assert the new paths and prove both release Python helpers are syntax checked.
- [x] A focused release module guide documents its purpose, development-only namespace, internal layout, stable
      command, extension rules, and verification contract; contributing documentation points to it without copying
      release policy.
- [x] The MkDocs override lives beneath `docs/overrides`, MkDocs uses that directory as `custom_dir`, and the
      override directory is excluded from ordinary documentation discovery and output.
- [x] A pinned Material for MkDocs build retains the custom not-found page and does not publish the override template
      as a normal documentation asset.
- [x] Package-surface and public-API evidence classify the relocation intentionally without adding the release
      namespace to the consumer API promise.

## Verification

Focused release unit, conformance, process, and journey suites; Composer development and clean `--no-dev` autoload
probes; PHP and Python syntax checks; PHPCS; PHPStan; Rector dry-run; Deptrac with uncovered and unassigned failures;
pinned MkDocs build and rendered-output inspection; `./bin/planning-check`; and the full `./bin/build` submit gate.

## Implementation Evidence

- Release source, command implementation, supporting Python helpers, tests, conformance cases, and fixtures now live
  beneath `release/`; Composer maps `Fight\Release\` and `Fight\Test\Release\` only through root `autoload-dev`.
- `bin/release` remains the sole stable executable. Existing inspect, plan, prepare, artifact-store, run-state,
  crash, credential-isolation, boundary, and canonical-runtime journeys retained their observable contracts.
- A clean offline Composer path consumer installs the real package with `--no-dev`, retains the shipped release
  files, registers `Fight\Common\`, omits `Fight\Release\`, and cannot resolve a representative release class.
- The canonical gate covers release PHP syntax, both Python helpers, PHPCS, PHPStan, Rector, Deptrac, PHPUnit, and
  exact coverage. Deptrac analyzed 469 tokens with no violations, skipped or uncovered dependencies, warnings,
  errors, or unassigned declarations; a focused fixture rejects runtime Application dependencies on Release.
- `./bin/build` passed 3,519 tests with 12,024 assertions and exact 13,886/13,886 statement coverage. Independent
  Standards and Spec reviews reported no blocking finding; Spec reported no finding at all.
- Material for MkDocs 9.7.7 passes in strict mode, renders the custom `site/404.html`, and emits no
  `site/overrides/404.html`. Contributor documentation no longer links outside the published documentation tree.

## Parent

PRD-00019 — Isolate Release Tooling from the Consumer Runtime Surface.
