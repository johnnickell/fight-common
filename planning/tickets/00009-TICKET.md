---
id: T-00009
prd: PRD-00004
title: Implement EventSourcedRepository
status: ready-for-agent
blocked_by: T-00004,T-00007
---

# Implement EventSourcedRepository

## What to Build

Let an application save a consumer aggregate and later find and reconstitute it through one stable aggregate definition, while keeping storage envelopes, mappings, schemas, and technical metadata outside the domain model.

## Blocked By

- T-00004 — Implement aggregate lifecycle.
- T-00007 — Implement the in-memory Event Store.

## Acceptance

- [ ] Each repository owns one `AggregateDefinition` pairing a stable aggregate name with the current aggregate class and depends on the framework-free aggregate interface.
- [ ] `find(Identifier)` returns null for an empty stream.
- [ ] A non-empty stream is unwrapped into ordered plain event payloads and passed to the aggregate's static `reconstitute()` contract.
- [ ] Save computes expected version as current aggregate version minus the released batch size.
- [ ] Saving an empty released batch is a no-op.
- [ ] A save failure invalidates the released aggregate instance; callers discard and reload rather than retrying that instance.
- [ ] Repository behavior remains unaware of stored aliases, schemas, metadata, and upcasters, which stay inside the Event Store.
- [ ] The complete save/find/reconstitution journey has complete coverage through the public repository seam.
