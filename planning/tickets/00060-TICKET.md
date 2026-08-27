---
id: T-00060
prd: PRD-00014
title: Publish Private Realtime Updates Through Mercure
status: done
blocked_by: T-00047
---

# Publish Private Realtime Updates Through Mercure

## What to Build

Publish an authorization-sensitive realtime journey through a separate `PrivatePublisher` port and one reusable
Mercure adapter. Consumers choose private publication explicitly while every existing public `Publisher`
consumer keeps the same signature and behavior; authorization, payload transformation, and framework-owned
realtime operations remain outside the adapter.

## Acceptance Criteria

- [x] `PrivatePublisher` exposes `pushPrivate(string $topic, string $message)` independently from the existing
      public `Publisher` contract.
- [x] `Publisher::push()` and every existing public publisher implementation retain their supported `1.x`
      signatures and behavior unchanged.
- [x] The reusable Mercure adapter publishes a private update through the supported client protocol. Mercure Hub
      compatibility configuration, including compatibility mode disabled, remains starter-owned composition evidence
      rather than a Fight Common client claim.
- [x] Transport failures fail closed through the designated socket exception contract and retain their causal
      exception without being reported as successful publication.
- [x] The adapter accepts an already-authorized topic and prepared message; it does not authorize subscribers,
      transform public envelopes, select application policy, or manage browser subscriptions.
- [x] Public and private publishers can be constructed and selected independently so private behavior does not
      silently broaden a public publication path.
- [x] Laravel Reverb and framework-specific hub, credential, cookie, queue, and worker composition remain
      starter-owned rather than becoming new Fight Common abstractions.
- [x] Optional Mercure package metadata and documentation make activation discoverable without introducing an
      unconditional production dependency.
- [x] Installed-package probes prove the unchanged public path, the independent private path, private Mercure
      publication, and fail-closed transport behavior using only public package APIs.
- [x] The public API manifest and compatibility findings classify `PrivatePublisher`, the Mercure adapter, and
      the unchanged `Publisher` contract deliberately.

## Verification

Full submit gate, `./bin/planning-check`, focused public and private publisher tests, client-protocol private-update
receipts, optional-package isolation checks, and installed-package consumer probes. A supported starter's Hub
integration receipt proves its compatibility-mode-disabled deployment configuration.

## Implementation Evidence

2026-08-26: Added the independent `PrivatePublisher` port and `PrivateMercureHubPublisher`; retained the unchanged
public `Publisher`/`MercureHubPublisher` journey; classified the additions in the public API manifest; and added
documentation plus copied-package public/private Mercure probes. `./bin/planning-check`, `./bin/build`, and
`git diff --check` passed. Hub compatibility-mode configuration remains receiving-starter deployment evidence.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.
