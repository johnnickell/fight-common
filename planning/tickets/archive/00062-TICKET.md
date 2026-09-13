---
id: T-00062
prd: PRD-00016
title: Bootstrap the Public Symfony Starter and Transfer Authority
status: done
blocked_by:
---

# Bootstrap the Public Symfony Starter and Transfer Authority

## What to Build

Establish `johnnickell/project-symfony` as the public-source Symfony implementation home. Prove a native
Symfony foundation can consume Fight Common and Fight AccessControl through their public package boundaries,
then transfer detailed starter planning and implementation authority to the repository without extracting the
login slice or claiming release readiness during bootstrap.

## Acceptance Criteria

- [x] The repository exists under the approved Composer identity as public source and consumes Fight Common and
      Fight AccessControl without copied Domain or Application source.
- [x] Symfony owns service loading, compiler integration, aliases, environment configuration, HTTP and security
      composition, and every future persistence or presentation adapter; no Fight Common bundle is introduced.
- [x] Repository-local agent guidance, architecture rules, planning artifacts, triage states, and completion
      authority are canonical and link back to PRD-00016 and PRD-00018.
- [x] PRD-00018 is handed off as the local product and walking-slice authority, with the Symfony login extraction
      and later capability tickets remaining repository-local after bootstrap.
- [x] One noninteractive build verifies the native framework skeleton, declared Fight package dependencies,
      architecture, tests, documentation, production installation, and absence of copied shared layers.
- [x] Hosted CI delegates to the canonical build; MIT licensing, security policy, contribution guidance, and
      public-source expectations are explicit.
- [x] A clean-clone receipt binds the successful build to an immutable commit reference.
- [x] The bootstrap does not require login, a browser journey, a tag, Packagist publication,
      template enablement, or create-project distribution.
- [x] Fight Common retains only the canonical repository-plan link, immutable bootstrap receipt, and dependency
      edge rather than local implementation criteria or status.

## Verification

Repository-local full build and hosted-gate receipt, clean-clone Composer installation, native Symfony
composition and architecture checks, immutable commit verification, and Fight Common
`./bin/planning-check`.

## Bootstrap receipt

`johnnickell/project-symfony` is public source. Foundation commit
`34701c1964f92746b62599bcb46de0d245107dbb` supplies the canonical planning, native Symfony composition,
clean production-install verification, and `./bin/build` receipt. Its six PHPUnit tests make 65 assertions.
Hosted [Build run 32214076293](https://github.com/johnnickell/project-symfony/actions/runs/32214076293)
passed on `develop` merge commit `438ff560e01bb3458729ead5f4dce3c1d88b150c`; [project-symfony PR #1](https://github.com/johnnickell/project-symfony/pull/1)
records the merged handoff. T-00062 is accepted. The Symfony repository now owns its PRD-00018 capability
tickets and release decisions; this bootstrap does not authorize a release tag, Packagist publication, template
enablement, or create-project distribution.

## Parent

PRD-00016 — Fight Package and Starter Repository Ownership.
