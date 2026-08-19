---
id: T-00063
prd: PRD-00016
title: Bootstrap the Public Laravel Starter and Transfer Authority
status: done
blocked_by: T-00061
---

# Bootstrap the Public Laravel Starter and Transfer Authority

## What to Build

Establish `johnnickell/project-laravel` as the public-source Laravel implementation home. Prove a native
Laravel foundation can consume Fight Common and Fight AccessControl through their public package boundaries,
then transfer detailed starter planning and implementation authority to the repository without beginning the
login slice or claiming release readiness during bootstrap.

## Acceptance Criteria

- [x] The repository exists under the approved Composer identity as public source and consumes Fight Common and
      Fight AccessControl without copied Domain or Application source.
- [x] Laravel owns service-provider, container, HTTP, security, persistence, queue, realtime, presentation, and
      operational composition rather than receiving framework behavior from either shared package.
- [x] Repository-local agent guidance, architecture rules, planning artifacts, triage states, and completion
      authority are canonical and link back to PRD-00016 and PRD-00018.
- [x] PRD-00018 is handed off as the local product and walking-slice authority, with login and later capability
      tickets remaining repository-local after bootstrap.
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

Repository-local full build and hosted-gate receipt, clean-clone Composer installation, native Laravel
composition and architecture checks, immutable commit verification, and Fight Common
`./bin/planning-check`.

## Bootstrap receipt

`johnnickell/project-laravel` is public source. Foundation commit
`76411ee5a3409209759db47bbbdbbc8d24d21ad6` supplied its canonical local planning, native Laravel
composition, and production-install verification. Hosted [Build run 32220554010](https://github.com/johnnickell/project-laravel/actions/runs/32220554010)
passed the canonical `./bin/build`; [project-laravel PR #1](https://github.com/johnnickell/project-laravel/pull/1)
merged that foundation to `develop` as `b3987b162787f803a1d40077bae13b5b4194810f`. T-00063 is accepted.
The Laravel repository owns its PRD-00018 capability tickets and release decisions; this bootstrap does not
authorize a release tag, Packagist publication, template enablement, or create-project distribution.

## Parent

PRD-00016 — Fight Package and Starter Repository Ownership.
