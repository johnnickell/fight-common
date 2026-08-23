---
id: T-00075
prd: PRD-00015
title: Compose the Five Booted Starter Support Receipts
status: ready-for-agent
blocked_by: T-00058
---

# Compose the Five Booted Starter Support Receipts

## What to Build

Consume one immutable lowest- and latest-resolution installed-package receipt from each real Symfony, Laravel,
Yii, CodeIgniter, and Slim starter. Fight Common can then prove that the candidate package installs with only the
selected framework stack, activates its documented capabilities through the native container, and completes the
representative lifecycle journeys required by that framework's support claim.

## Acceptance Criteria

- [ ] Each starter receipt identifies the Fight Common candidate, exact framework and provider versions, lock
      digest, selected capabilities, adapter states, executed journeys, result, and immutable evidence identity.
- [ ] Each starter resolves and executes both the lowest and latest permitted dependency sets rather than
      reporting dependency solving alone as success.
- [ ] Symfony proves compiler-pass identity, Messenger serialization, native response creation, routing,
      transactions, and representative provider adapters.
- [ ] Laravel proves selected service providers, complete queued-message delivery, transactions, native response
      and routing, and representative shipped and fallback adapters.
- [ ] Yii proves selected configuration/providers, transactions, routing, shared PSR/provider composition, native
      prototype outcomes, and stable Queue support as unavailable rather than skipped.
- [ ] CodeIgniter proves selected service delegates, complete Queue delivery, transactions, native response and
      routing, and representative shipped and fallback adapters.
- [ ] Slim proves explicit Fight-container registrars, shared PSR HTTP/cache/client composition, named routing,
      synchronous messaging, and selected provider adapters without branded shared-adapter copies.
- [ ] Every starter installs only its own framework and selected provider stack; the other four frameworks and
      unselected optional packages remain absent from its runtime graph.
- [ ] Receipts originate in the repository that owns the booted starter journey; Fight Common consumes and binds
      them without recreating five nested applications or centralizing their builds.
- [ ] Missing, unavailable, skipped, failed, conflicted, stale, or indeterminate required evidence fails the
      affected support claim and exposes exactly one resumable next action.
- [ ] Any proposal to weaken the two-part library-conformance plus starter-receipt rule requires a new explicit
      planning decision rather than a test or documentation exception.

## Verification

`./bin/planning-check`, receipt schema and identity tests, lowest/latest and capability-matrix reconciliation,
cross-repository evidence-link validation, deterministic missing/failure fixtures, and composition into T-00056.

## Parent

PRD-00015 — Framework Adapter Support and Capability Composition.

## Decision Sources

WF-024, ADR 0024, PRD-00016 repository ownership, and PRD-00018 starter acceptance.
