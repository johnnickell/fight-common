---
id: T-00017
prd: PRD-00006
title: Complete 1.2 compatibility and release acceptance
status: ready-for-agent
blocked_by: T-00016,T-00056
---

# Complete 1.2 Compatibility and Release Acceptance

## What to Build

Prove that the additive Fight Common release preserves existing CQRS and Event Sourcing contracts,
intentionally isolates message metadata, satisfies every portable and durable conformance guarantee, carries
the complete certified `1.2.0` compatibility envelope, and passes the repository's complete acceptance gate.

## Blocked By

- T-00016 — Document Event Sourcing integration and operations.
- T-00056 — Certify the Fight Common 1.2 compatibility envelope.

## Acceptance

- [ ] Existing public CQRS method signatures remain compatible, with message metadata isolation covered as an intentional behavioral change.
- [ ] Contract and adapter conformance suites cover all Event Sourcing, projection, dispatcher, and publication guarantees.
- [ ] Optional Symfony autoconfiguration does not block acceptance when it has not shipped.
- [ ] T-00056 supplies a successful content-addressed certification manifest covering the public API,
      Scheduler, JSend, namespace, dependency, framework-fixture, package, and archive evidence required for
      `1.2.0`.
- [ ] Planning validation and every non-interactive Docker submit gate pass with exact complete statement coverage.
- [x] Release notes target additive 1.2.0, explain the metadata behavior change, and do not alter existing tags.
- [ ] The epic, PRDs, tickets, board, documentation, and release surfaces agree on delivered and deferred scope.
