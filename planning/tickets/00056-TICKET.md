---
id: T-00056
prd: PRD-00014
title: Replace the Release Framework with Thin Certification
status: done
blocked_by:
---

# Replace the Release Framework with Thin Certification

## What to Build

Retain one real release-certification entry point while removing the simulated release framework, all release
process tests, and release code from everyday PHPUnit coverage. Product behavior remains protected by the normal
Fight Common suite; expensive compatibility and packaging proof runs only for an exact release candidate.

## Acceptance Criteria

- [x] `release/tests/` and the `Fight\Test\Release\` Composer namespace are removed.
- [x] PHPUnit and exact statement coverage include only consumer-runtime production code under `src/`.
- [x] All retained release PHP stays within syntax, PHPCS, PHPStan, and Rector checks; namespaced release module
      code remains inside the enforced Deptrac direction.
- [x] `./bin/release` accepts only `certify <version>` and requires a clean exact `HEAD`.
- [x] Certification runs locked, latest-compatible, and disposable lowest-compatible full product gates.
- [x] Certification creates a Composer archive and performs a clean `--no-dev` installed-consumer probe.
- [x] The consumer probe verifies representative public behavior and proves `Fight\Release\` is unavailable.
- [x] The installed package surface is compared with `compatibility/manifest.json`.
- [x] The compact certification record binds command outcomes, dependency versions, commit and archive digests,
      package-surface and consumer results, and the five accepted T-00075 starter receipt identities.
- [x] Certification performs no merge, tag, push, GitHub, Packagist, or deployment effect.
- [x] `./bin/build` passes with exact product coverage.
- [x] `./bin/release certify 1.2.0` succeeds for the separately authorized committed candidate, and its record is
      inspected against the exact commit, archive, three dependency lanes, consumer result, and receipt references.
- [x] Independent Standards and specification review accept the implementation against this contract.

## Verification

Run `./bin/build`, confirm the removed release test namespace and coverage paths are absent, then—after separate
commit authorization—run `./bin/release certify 1.2.0` for the clean exact commit. Inspect the installed package and
generated `.runs/handoffs/release-1.2.0-<commit>/certification.json`. Run `./bin/planning-check` and recalculate the
Board frontier. Certification, commit, push, pull request, merge, publication, and cleanup remain distinct effects.

## Completion Evidence

The canonical `./bin/build` passed with 3,641 tests, 20,620 assertions, and exact 10,089/10,089 statement
coverage. `./bin/release certify 1.2.0` then certified implementation commit
`301a94f58331465b312373c0bfd0ffc589279a36`: all eight commands passed, the three dependency lanes were recorded,
the clean installed consumer proved representative UUID and typed-collection behavior with `Fight\Release\`
unavailable, the package surface matched, and all five T-00075 receipt identities were cited. Independent
Standards and specification reviews accepted the implementation. The final planning-only PR head is recertified
as a separate ignored handoff before publication.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.

## Decision Source

ADR 0025 supersedes the simulated workflow portions of ADR 0014 while retaining the accepted compatibility and
publication boundaries from ADRs 0013 and 0016.
