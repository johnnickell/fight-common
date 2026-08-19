---
id: T-00066
prd: PRD-00016
title: Bootstrap the Public Slim Starter and Transfer Authority
status: done
blocked_by: T-00061
---

# Bootstrap the Public Slim Starter and Transfer Authority

## What to Build

Establish `johnnickell/project-slim` as the public-source Slim implementation home. Prove a native Slim
foundation can consume Fight Common and Fight AccessControl through their public package boundaries, then
transfer detailed starter planning and implementation authority to the repository without beginning the login
slice or claiming release readiness during bootstrap.

## Acceptance Criteria

- [x] The repository exists under the approved Composer identity as public source and consumes Fight Common and
      Fight AccessControl without copied Domain or Application source.
- [x] Slim owns explicit container definitions, handler maps, HTTP, security, Doctrine persistence, queue,
      presentation, and operational composition rather than receiving a shared framework bundle.
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

Repository-local full build and hosted-gate receipt, clean-clone Composer installation, native Slim composition
and architecture checks, immutable commit verification, and Fight Common `./bin/planning-check`.

## Bootstrap receipt

`johnnickell/project-slim` is public source. Foundation commit
`4958ba4992dc522afa37956f2eefb7a669403fda` supplies canonical local planning, explicit Slim composition,
and production-install verification. Hosted [Build run 32245221189](https://github.com/johnnickell/project-slim/actions/runs/32245221189)
passed the canonical `./bin/build`, including the clean-checkout build and clean production-install boundary;
[project-slim PR #3](https://github.com/johnnickell/project-slim/pull/3) merged that foundation to `develop` as
`1f845caea37df6579f9d84ebafd446969be50149`. T-00066 is accepted. The Slim repository owns its PRD-00018
capability tickets and release decisions; this bootstrap does not authorize a release tag, Packagist publication,
template enablement, or create-project distribution.

## Parent

PRD-00016 — Fight Package and Starter Repository Ownership.
