---
id: T-00064
prd: PRD-00016
title: Bootstrap the Public Yii Starter and Transfer Authority
status: done
blocked_by: T-00061
---

# Bootstrap the Public Yii Starter and Transfer Authority

## What to Build

Establish `johnnickell/project-yii` as the public-source Yii implementation home. Prove a native Yii
foundation can consume Fight Common and Fight AccessControl through their public package boundaries, then
transfer detailed starter planning and implementation authority to the repository without beginning the login
slice or claiming release readiness during bootstrap.

## Acceptance Criteria

- [x] The repository exists under the approved Composer identity as public source and consumes Fight Common and
      Fight AccessControl without copied Domain or Application source.
- [x] Yii owns its configuration provider, dependency-injection, HTTP, security, Active Record, queue,
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

Repository-local full build and hosted-gate receipt, clean-clone Composer installation, native Yii composition
and architecture checks, immutable commit verification, and Fight Common `./bin/planning-check`.

## Bootstrap receipt

`johnnickell/project-yii` is public source. Foundation commit
`fb8874d478b1ad1001cb9e3564e1cb28daf4f45b` supplied its canonical local planning, native Yii composition,
and production-install verification. Hosted [Build run 32229471630](https://github.com/johnnickell/project-yii/actions/runs/32229471630)
passed the canonical `./bin/build`; [project-yii PR #1](https://github.com/johnnickell/project-yii/pull/1)
merged that foundation to `develop` as `90aa598315d4a1cf8fb8626ae06ef2679269ebd4`. T-00064 is accepted.
The Yii repository owns its PRD-00018 capability tickets and release decisions; this bootstrap does not authorize
a release tag, Packagist publication, template enablement, or create-project distribution.

## Parent

PRD-00016 — Fight Package and Starter Repository Ownership.
