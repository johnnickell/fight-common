# Contributing & Branching Strategy

This document describes the long-term maintenance model for `johnnickell/fight-common`.

---

## Branch Model

```
main      ←── protected; every merge is a tagged stable release
1.1       ←── maintenance branch for 1.1.x bug fixes
1.0       ←── maintenance branch for 1.0.x bug fixes
develop   ←── integration branch for upcoming features
feature/* ←── short-lived; branched off develop
release/* ←── short-lived; branched off develop for release candidates
hotfix/*  ←── short-lived; branched off 1.0
```

### `main`

The default and protected branch. Every commit on `main` has a version tag. Direct pushes are blocked — changes land here only via PR from `release/*` (for minor/major releases) or from a maintenance branch (`1.0`, `1.1`, etc.) (for patch backports).

### `1.1`

Created from the `v1.1.0` tag. Accepts bug fixes and security patches for the 1.1 release line. No new features. PRs target `1.1`; the resulting commits are cherry-picked to `develop` to keep the branches in sync.

### `1.0`

Created from the `v1.0.0` tag. Accepts bug fixes and security patches for the 1.0 release line. No new features. PRs target `1.0`; the resulting commits are cherry-picked to `develop` to keep the branches in sync.

### `develop`

The active development branch for the next minor/major release. All feature work merges here first.

### `release/*`

Short-lived branches off `develop` for release preparation. Name them `release/<version>` (e.g. `release/1.1.0`). Use for CHANGELOG updates, version bumps, and final QA. Merge into `main` (via `--no-ff`) and back into `develop` (via `--no-ff`). Delete after merging.

### `feature/*`

Short-lived branches off `develop`. Name them `feature/short-description`. Delete after merging.

### `hotfix/*`

Short-lived branches off the relevant maintenance branch (`1.0`, `1.1`, etc.). Name them `hotfix/short-description`. Merge back into the maintenance branch, then cherry-pick the commit(s) to `develop`.

---

## Semantic Versioning

This library follows [semver](https://semver.org/):

| Change type | Version component | Branch target |
|-------------|------------------|---------------|
| Bug fix, no API change | **Patch** `1.x.y` | maintenance branch → cherry-pick to `develop` |
| New feature, backwards-compatible | **Minor** `x.y.0` | `main` via release branch |
| Breaking change | **Major** `x.0.0` | `main` via release branch, with deprecation notice first |

### What counts as a breaking change

- Removing or renaming a public interface, class, or method
- Changing a method signature (adding required parameters, changing return types)
- Raising the minimum PHP version
- Raising the minimum version of a required package in a way that drops support

### What does NOT count as a breaking change

- Adding a new class, interface, or method
- Adding optional parameters to an existing method
- Bug fixes that change incorrect behaviour to correct behaviour
- Internal refactors with no public API impact

---

## Release Process

### Patch release (1.0.x)

```bash
git checkout 1.0
git checkout -b hotfix/fix-description

# make changes, commit, ensure tests pass
git push origin hotfix/fix-description
# open PR → 1.0

# after merge:
git checkout 1.0 && git pull
git tag v1.0.1
git push origin v1.0.1

# backport to develop
git checkout develop
git cherry-pick <commit-sha>
git push origin develop
```

Update `CHANGELOG.md` under the new `[v1.0.1]` heading before tagging.

### Minor / major release

```bash
# ensure develop is green
git checkout develop
git checkout -b release/<version>

# update CHANGELOG.md and any version references
# run full submit gate

git commit -m "Release v<version>"
git checkout main
git merge --no-ff release/<version>
git tag v<version>
git push origin main v<version>

# back to develop
git checkout develop
git merge --no-ff release/<version>
git push origin develop

# cleanup
git branch -d release/<version>

# create maintenance branch
git checkout -b <major>.<minor> v<version>
git push origin <major>.<minor>
```

---

## Pull Request Requirements

- All PRs must pass the `Tests` GitHub Actions workflow
- 100% statement coverage must be maintained (enforced by `requireCoverageMetadata` in `phpunit.xml.dist`)
- Every new class in `src/` requires a corresponding test class with `#[CoversClass]`
- Follow the existing code style (PSR-12, enforced by PHP_CodeSniffer)
- PHPStan must pass at the configured level (currently level 6, enforced by CI)

---

## Local Development

All tooling runs inside the `fight-common` Docker container through the standard `./bin/` entrypoints:

```bash
./bin/phpunit                              # complete suite with disposable MySQL/PostgreSQL
./bin/phpunit --fast                       # fast suite; excludes server-database tests
./bin/phpunit --filter MyTest              # filter the complete suite
./bin/composer require vendor/package      # add a dependency
./bin/rector process src/                  # apply code modernization
./bin/exec php vendor/bin/phpstan analyse  # static analysis (level 6)
```

The default `./bin/build` installs the dependency versions recorded in `composer.lock` and runs the shared
quality gate in a disposable container. Use this reproducible path for ordinary local development and release
verification. Running `./bin/build --latest` explicitly updates the worktree's lockfile for review before an
intentional commit.

To opt into the tracked pre-commit build gate for this repository, configure Git's hooks path:

```bash
git config core.hooksPath .githooks
```

The hook runs the default `./bin/build` before each commit. When an explicit bypass is necessary, use Git's
standard `git commit --no-verify` escape hatch.

Hosted CI runs `composer update` ephemerally and invokes `./bin/quality` directly on the runner with disposable
MySQL and PostgreSQL services. The generated lockfile is evidence for that latest-compatible run only; CI does
not commit or publish it.

Complete mode provisions disposable MySQL 8.4.11 and PostgreSQL 17 services,
waits for health, injects both `FIGHT_COMMON_*_DSN` values, and cleans up its
containers and network on every exit. Unavailable complete-suite infrastructure
fails the run; it is never silently skipped. `--fast` is an optional local
feedback workflow and is not submit or release evidence.

See the [CLAUDE.md](../CLAUDE.md) for non-interactive (CI-style) Docker commands.
