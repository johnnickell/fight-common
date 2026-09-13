---
id: T-00073
prd: PRD-00015
title: Deliver CodeIgniter Queued Messaging Transactions and Service Delegates
status: done
blocked_by: T-00051,T-00059
---

# Deliver CodeIgniter Queued Messaging, Transactions, and Service Delegates

## What to Build

Deliver the CodeIgniter walking slice from complete Fight command and event messages through the official Queue
facility into neutral synchronous handlers, alongside a native database transactional UnitOfWork and
capability-scoped service delegates. A CodeIgniter application explicitly selects each bounded integration while
retaining native services and queue lifecycle.

## Acceptance Criteria

- [x] Queued command delivery transports one complete `CommandMessage` and delegates it unchanged to the neutral
      command-message handler and synchronous Fight bus.
- [x] Queued event delivery transports one complete `EventMessage` and delegates it unchanged to the neutral
      event-message handler and complete synchronous dispatcher fan-out.
- [x] Official Queue serialization, acknowledgement, and retry preserve message ID, creation time, payload type
      and value, and isolated metadata; repeated delivery retains the same event occurrence.
- [x] Post-commit submission is used where the official facility exposes it; otherwise the exact timing gap is
      reported before support is claimed. Delivery remains at least once and is not an atomic outbox.
- [x] Broker choice, queue names, retry and failure policy, failed-job storage, workers, topology, and outbox
      behavior remain starter or application configuration.
- [x] The native UnitOfWork preserves callback results, commits success, rolls back and rethrows the original
      failure, reports lifecycle consistently, and rejects nested portable transactions explicitly.
- [x] Transaction behavior passes the same conformance suite as Doctrine and Laravel without exposing native
      savepoint differences through the Fight contract.
- [x] Capability service delegates register only their bounded services, aliases, handlers, and collaborators and
      do not replace project-owned `Config\Services` policy.
- [x] Real CodeIgniter boot tests activate one selected service delegate at a time while unrelated capabilities
      and optional packages remain absent.
- [x] No inward contract gains a CodeIgniter dependency or framework-specific lifecycle operation.

## Verification

Full submit gate, `./bin/planning-check`, complete-envelope Queue round trips and retries, transaction conformance,
post-commit evidence, one-delegate-at-a-time CodeIgniter boot tests, and optional-package absence probes.

## Parent

PRD-00015 — Framework Adapter Support and Capability Composition.

## Decision Sources

WF-022, WF-024, and ADR 0024.
