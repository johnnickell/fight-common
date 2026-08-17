---
id: T-00062
prd: PRD-00016
title: Bootstrap the Private Symfony Starter and Transfer Authority
status: ready-for-agent
blocked_by: T-00061
---

# Bootstrap the Private Symfony Starter and Transfer Authority

## What to Build

Establish `johnnickell/project-symfony` as the initially private Symfony implementation home. Prove a native
Symfony foundation can consume Fight Common and Fight AccessControl through their public package boundaries,
then transfer detailed starter planning and implementation authority to the repository without extracting the
login slice or claiming release readiness during bootstrap.

## Acceptance Criteria

- [ ] The repository exists under the approved Composer identity, remains private, and consumes Fight Common and
      Fight AccessControl without copied Domain or Application source.
- [ ] Symfony owns service loading, compiler integration, aliases, environment configuration, HTTP and security
      composition, and every future persistence or presentation adapter; no Fight Common bundle is introduced.
- [ ] Repository-local agent guidance, architecture rules, planning artifacts, triage states, and completion
      authority are canonical and link back to PRD-00016 and PRD-00018.
- [ ] PRD-00018 is handed off as the local product and walking-slice authority, with the Symfony login extraction
      and later capability tickets remaining repository-local after bootstrap.
- [ ] One noninteractive build verifies the native framework skeleton, declared Fight package dependencies,
      architecture, tests, documentation, production installation, and absence of copied shared layers.
- [ ] Hosted CI delegates to the same canonical build, and licensing, security policy, contribution guidance,
      and private-incubation expectations are explicit.
- [ ] A clean-clone receipt binds the successful build to an immutable private commit reference.
- [ ] The bootstrap does not require login, a browser journey, public visibility, a tag, Packagist publication,
      template enablement, or create-project distribution.
- [ ] Fight Common retains only the canonical repository-plan link, immutable bootstrap receipt, and dependency
      edge rather than local implementation criteria or status.

## Verification

Repository-local full build and hosted-gate receipt, clean-clone Composer installation, native Symfony
composition and architecture checks, immutable private commit verification, and Fight Common
`./bin/planning-check`.

## Parent

PRD-00016 — Fight Package and Starter Repository Ownership.
