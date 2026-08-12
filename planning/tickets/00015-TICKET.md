---
id: T-00015
prd: PRD-00006
title: Add Symfony event-mapping autoconfiguration
status: done
blocked_by: T-00006
---

# Add Symfony Event-Mapping Autoconfiguration

## What to Build

Let Symfony consumers auto-discover bounded-context mapping providers and compose them into the configured Event Mapper while preserving the same portable registration and validation behavior used by manual construction.

## Blocked By

- T-00006 — Implement event mapping and upcasting.

## Acceptance

- [x] Symfony integration auto-tags `EventMappingProvider` implementations and composes them into the configured Event Mapper during container compilation.
- [x] Each provider contributes its durable namespace and typed mappings through the same portable core contract used by direct registration.
- [x] Duplicate or invalid mappings fail compiled-container mapper resolution through the Event Mapper's existing validation.
- [x] Direct construction and manual registration remain fully supported without Symfony.
- [x] Container behavior has complete coverage.
- [x] This ticket remains a non-release-blocking 1.2 stretch goal.

## Outcome

Added `EventMappingProviderCompilerPass`, which collects services tagged through Symfony autoconfiguration and
wires provider references into the configured `EventMapper` through its existing `registerProvider()` method.
Providers remain private and dependency-injectable; the compiler pass does not instantiate, reflect over, or
separately validate them. Resolving the mapper from the compiled container executes the portable registration
path, so duplicate aliases, duplicate classes, and invalid mappings continue to fail through
`EventMappingException`. Framework-free construction and manual provider registration are unchanged.

## Verification

- Focused container test: 5 tests and 12 assertions passed.
- Rector dry-run: clean across 416 production files.
- PHPStan: clean across 416 production files.
- PHPCS: clean.
- Deptrac: 0 violations, skipped violations, uncovered dependencies, warnings, or errors; no unassigned tokens.
- Complete PHPUnit gate: 3,079 tests and 5,581 assertions passed with disposable MySQL and PostgreSQL.
- New class coverage: 6/6 statements and 1/1 methods.
- Planning validation: clean after ticket, board, PRD, epic, and roadmap synchronization.
- Two-axis review: 0 Standards findings and 0 Spec findings.
