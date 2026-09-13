---
id: T-00087
prd: PRD-00018
title: Transfer the Canonical Pre-Submit Quality Gate to Every Starter
status: done
blocked_by:
---

# Transfer the Canonical Pre-Submit Quality Gate to Every Starter

## Outcome

Create one repository-local implementation ticket in each Symfony, Laravel, Yii, CodeIgniter, and Slim starter
for the lean, complete pre-submit gate required by ADR 0026 and PRD-00018. Fight Common retains the portfolio
contract; each starter owns its framework-specific tooling, implementation, verification, and delivery state.

## Scope

- In scope: five local ticket handoffs, Fight Common 1.2 and PHPCS-standard adoption, framework-specific
  extensions, test topology and pruning, exact unit-coverage policy, hosted-CI delegation, release-boundary
  separation, and evidence links back to this tracker.
- Out of scope: implementing or publishing any starter gate from Fight Common, centralizing the five builds,
  replacing meaningful framework-native checks, retaining release qualification in ordinary builds, or treating
  one starter as proof for another.

## Acceptance Criteria

- [x] Symfony [T-00007](https://github.com/johnnickell/project-symfony/blob/develop/planning/tickets/00007-TICKET.md), Laravel [T-00006](https://github.com/johnnickell/project-laravel/blob/develop/planning/tickets/00006-TICKET.md), Yii [T-00007](https://github.com/johnnickell/project-yii/blob/develop/planning/tickets/00007-TICKET.md), CodeIgniter [T-00007](https://github.com/johnnickell/project-codeigniter/blob/develop/planning/tickets/00007-TICKET.md), and Slim [T-00006](https://github.com/johnnickell/project-slim/blob/develop/planning/tickets/00006-TICKET.md) each own an unblocked local implementation ticket under PRD-00002.
- [x] Each successor records the canonical `./bin/build`, package/PHPCS, static-analysis, direct Unit-only exact coverage, retained boundary/journey, CI-delegation, and release-qualification separation contract.
- [x] Each successor identifies its repository-specific certification cleanup inventory while preserving completed historical tickets as history.
- [x] This tracker stops at the planning handoff: each starter independently owns implementation, local build, hosted-CI evidence, review, commit, and publication state.

## Verification

- `./bin/planning-check` passes in Fight Common after all five repository-local ticket links are recorded.
- Each linked starter ticket names its focused checks, canonical `./bin/build`, exact coverage evidence, and hosted
  CI state separately.

## Completion Notes

Fight Common `v1.2.0` was independently qualified through T-00041. The five successor tickets above are the authoritative implementation frontier; no starter gate, local `./bin/build`, or hosted-CI result is claimed here.
