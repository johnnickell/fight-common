---
id: T-00017
prd: PRD-00006
title: Complete 1.2 compatibility and release acceptance
status: ready-for-agent
blocked_by: T-00016
---

# Complete 1.2 Compatibility and Release Acceptance

## What to Build

Prove that the additive Event Sourcing release preserves existing CQRS contracts, intentionally isolates message metadata, satisfies every portable and durable conformance guarantee, and passes the repository's complete acceptance gate.

## Blocked By

- T-00016 — Document Event Sourcing integration and operations.

## Acceptance

- [ ] Existing public CQRS method signatures remain compatible, with message metadata isolation covered as an intentional behavioral change.
- [ ] Contract and adapter conformance suites cover all Event Sourcing, projection, dispatcher, and publication guarantees.
- [ ] Optional Symfony autoconfiguration does not block acceptance when it has not shipped.
- [ ] Planning validation and every non-interactive Docker submit gate pass with exact complete statement coverage.
- [ ] Release notes target additive 1.2.0, explain the metadata behavior change, and do not alter existing tags.
- [ ] The epic, PRDs, tickets, board, documentation, and release surfaces agree on delivered and deferred scope.
