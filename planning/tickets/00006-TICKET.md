---
id: T-00006
prd: PRD-00003
title: Implement event mapping and upcasting
status: done
blocked_by: T-00005
---

# Implement Event Mapping and Upcasting

## What to Build

Let bounded contexts register stable stored event names and evolve persisted payload schemas while consumers continue to receive current event classes. Registration and history reads fail closed when identity or evolution is ambiguous.

## Blocked By

- T-00005 — Define stored-event and Event Store contracts.

## Acceptance

- [x] One Event Store owns an explicit bidirectional `EventMapper` containing every stored event.
- [x] Registration rejects duplicate aliases, duplicate classes, invalid schemas, and non-event classes.
- [x] Typed `EventMappingProvider` declarations contribute a durable namespace and local mappings without reflection, attributes, conventions, or FQCN fallback.
- [x] Stable event aliases remain independent of PHP class names, and class renames update registration without rewriting history or invoking an upcaster.
- [x] Every mapping validates a complete sequential one-to-one upcaster chain from schema version one to the current version.
- [x] Upcasting transforms stored payload data in memory without rewriting stored history.
- [x] Unknown aliases, future schemas, and incomplete upcast paths fail closed before current event hydration.
- [x] Mapping and upcasting behavior has complete coverage.

## Outcome

Added the framework-free `EventMapper`, `EventMapping`, `EventMappingProvider`, `MappedEvent`, and `Upcaster`
contracts with one shared direct/provider registration path. The mapper resolves current event messages to
stable storage names, schema versions, and payload data; reads apply a validated sequential upcaster chain in
memory before hydrating the current event class. Duplicate or invalid registration, unknown names or classes,
unsupported schemas, and incomplete chains fail through a dedicated `EventMappingException` without
reflection, conventions, FQCN fallback, or stored-history rewriting. T-00007 now supplies the first concrete
Event Store that owns this complete mapper, while T-00015 may collect providers through Symfony.

## Verification

- Rector dry-run: clean across 377 files.
- PHPStan: clean across 377 files.
- PHPCS: clean.
- PHPUnit: 2,856 tests and 4,442 assertions passed.
- Coverage: 7,682/7,682 statements and 1,717/1,717 methods.
- Planning validation: clean after ticket and board synchronization.
- Two-axis review: no Standards or Spec findings after five targeted refinements.
