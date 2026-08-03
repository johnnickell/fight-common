---
id: T-00009
prd: PRD-00004
title: Implement EventSourcedRepository
status: done
blocked_by: T-00004,T-00007
---

# Implement EventSourcedRepository

## What to Build

Let an application save a consumer aggregate and later find and reconstitute it through one stable aggregate definition, while keeping storage envelopes, mappings, schemas, and technical metadata outside the domain model.

## Blocked By

- T-00004 — Implement aggregate lifecycle.
- T-00007 — Implement the in-memory Event Store.

## Acceptance

- [x] Each repository owns one `AggregateDefinition` pairing a stable aggregate name with the current aggregate class and depends on the framework-free aggregate interface.
- [x] `find(Identifier)` returns null for an empty stream.
- [x] A non-empty stream is unwrapped into ordered plain event payloads and passed to the aggregate's static `reconstitute()` contract.
- [x] Save computes expected version as current aggregate version minus the released batch size.
- [x] Saving an empty released batch is a no-op.
- [x] A save failure invalidates the released aggregate instance; callers discard and reload rather than retrying that instance.
- [x] Repository behavior remains unaware of stored aliases, schemas, metadata, and upcasters, which stay inside the Event Store.
- [x] The complete save/find/reconstitution journey has complete coverage through the public repository seam.

## Outcome

Added a validated `AggregateDefinition` and a generic `EventSourcedRepository` over the framework-free
aggregate and Event Store contracts. Repositories now use stable aggregate names, return `null` for missing
history, unwrap ordered stored messages to plain current events for aggregate-owned reconstitution, and save
ordered released batches with the pre-batch expected version. Empty saves are no-ops, append failures preserve
the destructive release boundary, and repository code remains outside alias, schema, metadata, and upcasting
concerns.

## Verification

- Rector dry-run: clean across 383 source files.
- PHPStan: clean across 383 files.
- PHPCS: clean.
- PHPUnit: 2,926 tests and 4,641 assertions passed; 26 environment-dependent tests skipped.
- Coverage: 7,983/7,983 statements and 1,751/1,751 methods.
- Planning validation: clean with 42 records and 29 active after closure synchronization.
- Two-axis review: zero blocking Standards findings and zero Spec findings after three targeted refinements;
  one nonblocking test-local mapper-setup duplication judgment remains.
