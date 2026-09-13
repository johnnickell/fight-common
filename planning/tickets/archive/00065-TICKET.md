---
id: T-00065
prd: PRD-00016
title: Bootstrap the Public CodeIgniter Starter and Transfer Authority
status: done
blocked_by:
---

# Bootstrap the Public CodeIgniter Starter and Transfer Authority

## What to Build

Establish `johnnickell/project-codeigniter` as the public-source CodeIgniter implementation home. Prove a
native CodeIgniter foundation can consume Fight Common and Fight AccessControl through their public package
boundaries, then transfer detailed starter planning and implementation authority to the repository without
beginning the login slice or claiming release readiness during bootstrap.

## Acceptance Criteria

- [x] The repository exists under the approved Composer identity as public source and consumes Fight Common and
      Fight AccessControl without copied Domain or Application source.
- [x] CodeIgniter owns service configuration, module discovery, HTTP, security, persistence, queue,
      presentation, and operational composition rather than receiving framework behavior from a shared package.
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

Repository-local full build and hosted-gate receipt, clean-clone Composer installation, native CodeIgniter
composition and architecture checks, immutable commit verification, and Fight Common
`./bin/planning-check`.

## Bootstrap receipt

`johnnickell/project-codeigniter` is public source. Foundation commit
`fadb34e245007f159085ab40cc75b6810e700010` supplies canonical local planning, native CodeIgniter composition,
and production-install verification. The canonical `./bin/build` passed from an independent clean clone at that
commit on 2026-08-19, including governance, six PHPUnit tests with nine assertions, and the production Composer
public-dependency contract. Hosted [Build run 32234095998](https://github.com/johnnickell/project-codeigniter/actions/runs/32234095998)
passed on the `develop` merge commit; [project-codeigniter PR #1](https://github.com/johnnickell/project-codeigniter/pull/1)
records the merged handoff. T-00065 is accepted. The CodeIgniter repository now owns its PRD-00018 capability
tickets and release decisions; this bootstrap does not authorize a release tag, Packagist publication, template
enablement, or create-project distribution.

## Parent

PRD-00016 — Fight Package and Starter Repository Ownership.
