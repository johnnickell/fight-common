---
id: T-00007
prd: PRD-00003
title: Implement the in-memory Event Store
status: ready-for-agent
blocked_by: T-00006
---

# Implement the In-Memory Event Store

## What to Build

Provide an executable in-memory Event Store that consumers can use in tests and that defines the complete observable behavior every durable adapter must match.

## Blocked By

- T-00006 — Implement event mapping and upcasting.

## Acceptance

- [ ] Append atomically enforces expected versions, aggregate identity, and exact-batch message-ID idempotency.
- [ ] Append snapshots isolated message metadata, the original message timestamp, stable event aliases, and current schema versions through the Event Mapper.
- [ ] Stream and global reads preserve order and limits.
- [ ] Reads upcast before hydration and retain the original stored schema version in each `StoredEvent`.
- [ ] Store conformance tests cover success, empty streams, conflicts, exact retries, partial and misplaced retries, mapping failures, timestamp fidelity, and prefix-stable global visibility.
- [ ] The in-memory adapter becomes the executable reference behavior for durable stores.
- [ ] All behavior has complete coverage.
