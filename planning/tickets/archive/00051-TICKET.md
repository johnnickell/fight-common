---
id: T-00051
prd: PRD-00014
title: Publish Neutral Message Handlers and Canonical Symfony Messenger Paths
status: done
blocked_by: T-00047
---

# Publish Neutral Message Handlers and Canonical Symfony Messenger Paths

## What to Build

Publish framework-neutral command-message and event-message handlers that delegate complete Fight messages to
the synchronous bus or dispatcher, then publish the real Symfony Messenger command bus, event dispatcher, and
serializer under capability-first Symfony paths. Existing Symfony consumers retain every superseded public name
and established Messenger behavior throughout `1.x`.

## Acceptance Criteria

- [x] One neutral command-message handler accepts a complete `CommandMessage` and invokes the synchronous Fight
      `CommandBus` without recreating the message.
- [x] One neutral event-message handler accepts a complete `EventMessage` and invokes the synchronous Fight
      `EventDispatcher`, including its ordered complete fan-out behavior.
- [x] Handler conformance proves preservation of message ID, creation time, payload type and value, and isolated
      metadata across first delivery and repeated delivery.
- [x] Repeating an event delivery repeats the same event occurrence and complete synchronous fan-out; delivery is
      documented as at least once rather than exactly once.
- [x] The neutral handlers contain no Symfony, queue, broker, retry, worker, topology, or outbox policy.
- [x] MessengerCommandBus, MessengerEventDispatcher, and the Messenger serializer are published under canonical
      capability-first Symfony paths with unchanged routing and serialization behavior.
- [x] Complete command and event envelopes round-trip through the Symfony serializer without losing identity,
      timestamp, payload, or metadata.
- [x] Every superseded Symfony messaging and handler FQCN remains independently functional and documented as
      deprecated throughout `1.x` without a runtime notice.
- [x] Identity-sensitive Messenger registration, attributes, service tags, extension behavior, and old/new
      interoperability are proven with real Symfony components.
- [x] PSR-14 is not advertised as equivalent to Fight messaging, and transport operations remain outside the
      synchronous handler contract.

## Verification

Full submit gate, `./bin/planning-check`, neutral handler conformance, Symfony Messenger serialization and
routing integration, repeated-delivery fan-out tests, and installed-package old/new FQCN probes.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.

## Decision Sources

WF-023, WF-024, WF-025, and ADR 0024.
