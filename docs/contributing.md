# Contributing & Branching Strategy

This document describes the long-term maintenance model for `johnnickell/fight-common`.

---

## Branch Model

```
main      ←── protected; every merge is a tagged stable release
1.0       ←── maintenance branch for 1.0.x bug fixes
develop   ←── integration branch for upcoming features
feature/* ←── short-lived; branched off develop
hotfix/*  ←── short-lived; branched off 1.0
```

### `main`

The default and protected branch. Every commit on `main` has a version tag. Direct pushes are blocked — changes land here only via PR from `develop` (for minor/major releases) or from `1.0` (for patch backports).

### `1.0`

Created from the `1.0.0` tag. Accepts bug fixes and security patches for the 1.0 release line. No new features. PRs target `1.0`; the resulting commits are cherry-picked to `develop` to keep the branches in sync.

### `develop`

The active development branch. All feature work merges here first. When a minor or major release is ready, `develop` is merged to `main` via PR and tagged.

### `feature/*`

Short-lived branches off `develop`. Name them `feature/short-description`. Delete after merging.

### `hotfix/*`

Short-lived branches off `1.0`. Name them `hotfix/short-description`. Merge back into `1.0`, then cherry-pick the commit(s) to `develop`.

---

## Semantic Versioning

This library follows [semver](https://semver.org/):

| Change type | Version component | Branch target |
|-------------|------------------|---------------|
| Bug fix, no API change | **Patch** `1.0.x` | `1.0` → cherry-pick to `develop` |
| New feature, backwards-compatible | **Minor** `1.x.0` | `develop` → `main` |
| Breaking change | **Major** `x.0.0` | `develop` with deprecation notice first |

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
git tag 1.0.1
git push origin 1.0.1

# backport to develop
git checkout develop
git cherry-pick <commit-sha>
git push origin develop
```

Update `CHANGELOG.md` under the new `[1.0.1]` heading before tagging.

### Minor / major release

```bash
# ensure develop is green
git checkout main
git merge --no-ff develop
git tag 1.1.0
git push origin main 1.1.0
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

All tooling runs inside the `fight-common` Docker container. The `./bin/` scripts are wrappers for interactive use:

```bash
./bin/phpunit                              # full test suite with coverage
./bin/phpunit --filter MyTest              # single test
./bin/composer require vendor/package      # add a dependency
./bin/rector process src/                  # apply code modernization
./bin/exec php vendor/bin/phpstan analyse  # static analysis (level 6)
```

See the [CLAUDE.md](../CLAUDE.md) for non-interactive (CI-style) Docker commands.
