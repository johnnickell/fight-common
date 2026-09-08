---
id: T-00075
prd: PRD-00015
title: Compose the Five Booted Starter Support Receipts
status: done
blocked_by: T-00058
---

# Compose the Five Booted Starter Support Receipts

## What to Build

Consume one immutable lowest- and latest-resolution installed-package receipt from each real Symfony, Laravel,
Yii, CodeIgniter, and Slim starter. Fight Common can then prove that the candidate package installs with only the
selected framework stack, activates its documented capabilities through the native container, and completes the
representative lifecycle journeys required by that framework's support claim.

The first committed slice owns the versioned receipt-v1 schema, validator, canonical starter path, and immutable
pin contract. It does not manufacture starter evidence; composition remains in progress until each repository's
local adoption ticket supplies an eligible receipt.

## Acceptance Criteria

- [x] Each starter receipt identifies the Fight Common candidate, exact framework and provider versions, lock
      digest, selected capabilities, adapter states, executed journeys, result, and immutable evidence identity.
- [x] Each starter resolves and executes both the lowest and latest permitted dependency sets rather than
      reporting dependency solving alone as success.
- [x] Symfony proves compiler-pass identity, Messenger serialization, native response creation, routing,
      transactions, and representative provider adapters.
- [x] Laravel proves selected service providers, complete queued-message delivery, transactions, native response
      and routing, and representative shipped and fallback adapters.
- [x] Yii proves selected configuration/providers, transactions, routing, shared PSR/provider composition, native
      prototype outcomes, and stable Queue support as unavailable rather than skipped.
- [x] CodeIgniter proves selected service delegates, complete Queue delivery, transactions, native response and
      routing, and representative shipped and fallback adapters.
- [x] Slim proves explicit Fight-container registrars, shared PSR HTTP/cache/client composition, named routing,
      synchronous messaging, and selected provider adapters without branded shared-adapter copies.
- [x] Every starter installs only its own framework and selected provider stack; the other four frameworks and
      unselected optional packages remain absent from its runtime graph.
- [x] Receipts originate in the repository that owns the booted starter journey; Fight Common consumes and binds
      them without recreating five nested applications or centralizing their builds.
- [x] Missing, unavailable, skipped, failed, conflicted, stale, or indeterminate required evidence fails the
      affected support claim and exposes exactly one resumable next action.
- [x] Any proposal to weaken the two-part library-conformance plus starter-receipt rule requires a new explicit
      planning decision rather than a test or documentation exception.

## Verification

`./bin/planning-check`, receipt schema and identity tests, lowest/latest and capability-matrix reconciliation,
cross-repository evidence-link validation, deterministic missing/failure fixtures, and composition into T-00056.

## Completion Evidence

Each repository-owned receipt is bound below to the merged `develop` commit that supplied the accepted booted
journeys. The immutable receipt itself carries the exact framework and provider versions, lock digest, capability
states, journey results, and independent evidence digest.

| Starter | Merged receipt | Content ID | Fight Common candidate |
| --- | --- | --- | --- |
| Symfony | [project-symfony PR #6 at `ade02de`](https://github.com/johnnickell/project-symfony/blob/ade02de762c6dd049f3183a1137a83247740dbdd/evidence/framework-support/receipt-v1.json) | `1c71f6fc74a73a1976b562043630d403bb7e873b5049d64c6d5705b098b027fe` | `4a798b1db8fdb5e4af7d0ba8c98a88ac53c50c16` |
| Laravel | [project-laravel PR #5 at `19c7d83`](https://github.com/johnnickell/project-laravel/blob/19c7d839da0d6bd579f83a34950ed2433bff44dc/evidence/framework-support/receipt-v1.json) | `bdda487f5ae57a6137b962be83680361be5d40967dc739d3ce2c6cc75842b8d6` | `ceae16393fd15a2a20687b7533dc048ab1f6a1af` |
| Yii | [project-yii PR #4 at `7414dfb`](https://github.com/johnnickell/project-yii/blob/7414dfb8800c0b4506ee10ff324c8eecc65f0d3c/evidence/framework-support/receipt-v1.json) | `9085309c84f26cd5c48e771b8f2746912515c134c371a3f419bbb1ec2009e717` | `4a798b1db8fdb5e4af7d0ba8c98a88ac53c50c16` |
| CodeIgniter | [project-codeigniter PR #5 at `9cd90fd`](https://github.com/johnnickell/project-codeigniter/blob/9cd90fd17938eeca3562e0fe2107decee2190783/evidence/framework-support/receipt-v1.json) | `6b8e71456054721f1989043acb7a53738553f83daa5d0bcd793417922ec83124` | `4a798b1db8fdb5e4af7d0ba8c98a88ac53c50c16` |
| Slim | [project-slim PR #8 at `243c018`](https://github.com/johnnickell/project-slim/blob/243c018869e7f833f2bc5a19d1402d6d8c5c45ab/evidence/framework-support/receipt-v1.json) | `3dc8b643c50a62fd9bf6fdb120d596a734cb0fca1b5c82d5d96ccc8b731d87b7` | `4a798b1db8fdb5e4af7d0ba8c98a88ac53c50c16` |

All five receipts report `passed`, retain one repository-owned latest and lowest execution lane, and were accepted
through independent code review before merge. The starter repositories remain the evidence owners; Fight Common
records immutable links and identities without copying their applications or centralizing their builds. T-00056
now owns composition of these five accepted receipts into the final `1.2.0` compatibility certification.

## Parent

PRD-00015 — Framework Adapter Support and Capability Composition.

## Decision Sources

WF-024, ADR 0024, PRD-00016 repository ownership, and PRD-00018 starter acceptance.
