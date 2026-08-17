---
id: T-00061
prd: PRD-00016
title: Bootstrap the Fight AccessControl Repository and Transfer Authority
status: in-progress
blocked_by:
---

# Bootstrap the Fight AccessControl Repository and Transfer Authority

## What to Build

Establish `johnnickell/fight-access-control` as the framework-neutral home for shared
identity, credential, session, authorization, and account-lifecycle behavior. Transfer detailed planning and
implementation authority from Fight Common to a repository-local contract, and prove the new package can build
cleanly against Fight Common without acquiring a production Adapter layer. Public source visibility and MIT
licensing do not authorize a version tag, Packagist publication, or release.

## Acceptance Criteria

- [ ] The repository exists under the approved Composer identity, its public history passes a credential and
      private-path audit, and the source is available under the MIT License.
- [ ] The production package skeleton contains only framework-neutral Domain and Application boundaries, depends
      on Fight Common through public contracts, and rejects framework packages and a production Adapter layer.
- [ ] Repository-local agent guidance, architecture rules, planning artifacts, triage states, and completion
      authority are canonical and preserve PRD-00016 and PRD-00017 provenance without requiring Fight Common for
      local implementation status.
- [ ] PRD-00017 is handed off as local PRD-00001 behavioral and security authority, and detailed capability
      tickets are created only in Fight AccessControl after that authority exists.
- [ ] One noninteractive build verifies package installation, dependency direction, architecture, tests,
      documentation, and production autoloading without a framework package installed.
- [ ] Hosted CI delegates to the same canonical build rather than maintaining a second completion contract.
- [ ] MIT licensing, security policy, contribution guidance, and public-source-incubation expectations are
      explicit without inferring a version tag, Packagist publication, or release.
- [ ] A clean-clone receipt binds the successful build to an immutable public commit reference.
- [ ] Fight Common retains only the canonical repository-plan link, immutable bootstrap receipt, and
      cross-repository dependency edge; it does not copy local acceptance criteria or status.

## Verification

Repository-local full build and hosted-gate receipt, clean-clone package installation, architecture and
dependency checks, immutable public commit verification, public-surface audit, and Fight Common
`./bin/planning-check`. No release tag, Packagist publication, or production capability implementation is
required.

## Bootstrap receipt

Public source visibility and MIT licensing were explicitly approved on 2026-08-17. Commit
`60e67ad5d8a45ecc11f1f2f4cf6d5dc7f3adbc17` passes the canonical build from a disposable public clean clone
with 31 tests and 196 assertions. Hosted [PHP 8.5 run 32056020022](https://github.com/johnnickell/fight-access-control/actions/runs/32056020022)
passes the same latest-compatible quality gate. The remaining acceptance step is merging
[Fight AccessControl PR #1](https://github.com/johnnickell/fight-access-control/pull/1), recording its merged
immutable commit, and synchronizing this ticket and the board.

## Parent

PRD-00016 — Fight Package and Starter Repository Ownership.
