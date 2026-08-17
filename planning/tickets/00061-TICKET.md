---
id: T-00061
prd: PRD-00016
title: Bootstrap the Private Fight AccessControl Repository and Transfer Authority
status: ready-for-agent
blocked_by:
---

# Bootstrap the Private Fight AccessControl Repository and Transfer Authority

## What to Build

Establish `johnnickell/fight-access-control` as the initially private, framework-neutral home for shared
identity, credential, session, authorization, and account-lifecycle behavior. Transfer detailed planning and
implementation authority from Fight Common to a repository-local contract, and prove the new package can build
cleanly against Fight Common without acquiring a production Adapter layer or authorizing public distribution.

## Acceptance Criteria

- [ ] The repository exists under the approved Composer identity and remains private throughout the bootstrap.
- [ ] The production package skeleton contains only framework-neutral Domain and Application boundaries, depends
      on Fight Common through public contracts, and rejects framework packages and a production Adapter layer.
- [ ] Repository-local agent guidance, architecture rules, planning artifacts, triage states, and completion
      authority are canonical and link back to PRD-00016 and PRD-00017 without requiring Fight Common for local
      implementation status.
- [ ] PRD-00017 is handed off as the local behavioral and security authority, and detailed capability tickets are
      created only in Fight AccessControl after that authority exists.
- [ ] One noninteractive build verifies package installation, dependency direction, architecture, tests,
      documentation, and production autoloading without a framework package installed.
- [ ] Hosted CI delegates to the same canonical build rather than maintaining a second completion contract.
- [ ] Licensing, security policy, contribution guidance, and private-incubation expectations are explicit without
      inferring public visibility, a version tag, Packagist publication, or another distribution effect.
- [ ] A clean-clone receipt binds the successful build to an immutable private commit reference.
- [ ] Fight Common retains only the canonical repository-plan link, immutable bootstrap receipt, and
      cross-repository dependency edge; it does not copy local acceptance criteria or status.

## Verification

Repository-local full build and hosted-gate receipt, clean-clone package installation, architecture and
dependency checks, immutable private commit verification, and Fight Common `./bin/planning-check`. No public
visibility, release tag, Packagist publication, or production capability implementation is required.

## Parent

PRD-00016 — Fight Package and Starter Repository Ownership.
