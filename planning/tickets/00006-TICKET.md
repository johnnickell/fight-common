---
id: T-00006
prd: PRD-00003
title: Implement event mapping and upcasting
status: ready-for-agent
blocked_by: T-00005
---

# Implement Event Mapping and Upcasting

## What to Build

Let bounded contexts register stable stored event names and evolve persisted payload schemas while consumers continue to receive current event classes. Registration and history reads fail closed when identity or evolution is ambiguous.

## Blocked By

- T-00005 — Define stored-event and Event Store contracts.

## Acceptance

- [ ] One Event Store owns an explicit bidirectional `EventMapper` containing every stored event.
- [ ] Registration rejects duplicate aliases, duplicate classes, invalid schemas, and non-event classes.
- [ ] Typed `EventMappingProvider` declarations contribute a durable namespace and local mappings without reflection, attributes, conventions, or FQCN fallback.
- [ ] Stable event aliases remain independent of PHP class names, and class renames update registration without rewriting history or invoking an upcaster.
- [ ] Every mapping validates a complete sequential one-to-one upcaster chain from schema version one to the current version.
- [ ] Upcasting transforms stored payload data in memory without rewriting stored history.
- [ ] Unknown aliases, future schemas, and incomplete upcast paths fail closed before current event hydration.
- [ ] Mapping and upcasting behavior has complete coverage.
