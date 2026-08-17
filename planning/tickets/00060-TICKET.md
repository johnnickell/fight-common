---
id: T-00060
prd: PRD-00014
title: Publish Private Realtime Updates Through Mercure
status: ready-for-agent
blocked_by: T-00047
---

# Publish Private Realtime Updates Through Mercure

## What to Build

Publish an authorization-sensitive realtime journey through a separate `PrivatePublisher` port and one reusable
Mercure adapter. Consumers choose private publication explicitly while every existing public `Publisher`
consumer keeps the same signature and behavior; authorization, payload transformation, and framework-owned
realtime operations remain outside the adapter.

## Acceptance Criteria

- [ ] `PrivatePublisher` exposes `pushPrivate(string $topic, string $message)` independently from the existing
      public `Publisher` contract.
- [ ] `Publisher::push()` and every existing public publisher implementation retain their supported `1.x`
      signatures and behavior unchanged.
- [ ] The reusable Mercure adapter publishes a private update through the approved supported protocol with
      compatibility mode disabled.
- [ ] Transport failures fail closed through the designated socket exception contract and retain their causal
      exception without being reported as successful publication.
- [ ] The adapter accepts an already-authorized topic and prepared message; it does not authorize subscribers,
      transform public envelopes, select application policy, or manage browser subscriptions.
- [ ] Public and private publishers can be constructed and selected independently so private behavior does not
      silently broaden a public publication path.
- [ ] Laravel Reverb and framework-specific hub, credential, cookie, queue, and worker composition remain
      starter-owned rather than becoming new Fight Common abstractions.
- [ ] Optional Mercure package metadata and documentation make activation discoverable without introducing an
      unconditional production dependency.
- [ ] Installed-package probes prove the unchanged public path, the independent private path, private Mercure
      publication, and fail-closed transport behavior using only public package APIs.
- [ ] The public API manifest and compatibility findings classify `PrivatePublisher`, the Mercure adapter, and
      the unchanged `Publisher` contract deliberately.

## Verification

Full submit gate, `./bin/planning-check`, focused public and private publisher tests, supported-protocol Mercure
integration receipts with compatibility mode disabled, optional-package isolation checks, and installed-package
consumer probes.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.
