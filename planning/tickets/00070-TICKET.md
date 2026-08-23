---
id: T-00070
prd: PRD-00015
title: Deliver Laravel Queued Messaging Transactions and Service Providers
status: ready-for-agent
blocked_by: T-00051,T-00059
---

# Deliver Laravel Queued Messaging, Transactions, and Service Providers

## What to Build

Deliver the Laravel walking slice from a complete Fight command or event message through a queued Laravel job or
listener into the neutral synchronous handler, alongside an Illuminate database transactional UnitOfWork and
capability-scoped service providers. A Laravel project selects only messaging, persistence, or another bounded
capability and retains native queue and container lifecycle behavior.

## Acceptance Criteria

- [ ] Queued command delivery transports one complete `CommandMessage` and delegates it unchanged to the neutral
      command-message handler and synchronous Fight bus.
- [ ] Queued event delivery transports one complete `EventMessage` and delegates it unchanged to the neutral
      event-message handler and complete synchronous dispatcher fan-out.
- [ ] Queue serialization and retry preserve message ID, creation time, payload type and value, and isolated
      metadata; repeated delivery retains the same message occurrence.
- [ ] Submission uses Laravel's post-commit queue behavior where available and is documented as at least once,
      not as an atomic outbox.
- [ ] Broker choice, queue names, retries, failed-job policy, worker supervision, topology, and outbox behavior
      remain application or starter configuration.
- [ ] The Laravel UnitOfWork preserves callback results, commits success, rolls back and rethrows the original
      failure, reports terminal lifecycle consistently, and rejects nested portable transactions explicitly.
- [ ] Transaction behavior passes the same conformance suite as Doctrine and other native implementations rather
      than inheriting framework-specific savepoint semantics.
- [ ] Messaging and persistence service providers are independently selectable and register only their bounded
      public services, aliases, handlers, and collaborators.
- [ ] Provider tests boot a real Laravel application and prove that unrelated capabilities and optional packages
      are not activated.
- [ ] Queue and UnitOfWork adapters depend only outward on Laravel and preserve the Fight Domain and Application
      contracts unchanged.

## Verification

Full submit gate, `./bin/planning-check`, complete-envelope queue round trips and retries, post-commit tests,
shared transaction conformance, one-provider-at-a-time booted Laravel tests, and optional-package absence probes.

## Parent

PRD-00015 — Framework Adapter Support and Capability Composition.

## Decision Sources

WF-020, WF-024, and ADR 0024.
